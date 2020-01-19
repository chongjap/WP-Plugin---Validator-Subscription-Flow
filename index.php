<?php

/*
Plugin Name: Validator Subscription Flow
Description: Validator Subscription Flow
Author: Jesus Chong
Version: 1.0
License: GPL2
*/


    /*
    * Función para añadir una página al menú de administrador de wordpress
    */
    function vsf_plugin_menu(){
        //Añade una página de menú a wordpress
        add_menu_page('Ajustes Plugin Validator Subscription Flow',                //Título de la página
                        'Flow Integración',                             //Título del menú
                        'administrator',                                    //Rol que puede acceder
                        'vsf-settings',            //Id de la página de opciones
                        'vsf_content_page_settings',       //Función que pinta la página de configuración del plugin
                       // 'dashicons-chart-line'   //Icono del menú
                       //plugin_dir_url( __FILE__ ) . 'images/logo.png',
                       'dashicons-shield-alt',
                       80
                    );                          
    }
    add_action('admin_menu','vsf_plugin_menu');

    /*
    * Función que pinta la página de configuración del plugin
    */
    function vsf_content_page_settings(){
        ?>
            <div class="container-vsf">
                <div class="wrap" style="width: 48%; display: inline-block;" >
                    <div>
                        <h2>Configuración de Subscripción</h2>
                        <form method="POST" action="options.php">
                            <?php 
                                settings_fields('vsf-settings-group');
                                do_settings_sections( 'vsf-settings-group' ); 
                            ?>
                            <br>
                            <label style="width: 130px; display: inline-block;"  for="vsf-user">Tiempo de subscripción (días):&nbsp;</label>
                            <input  type="number" 
                                    name="vsf-time-subscription" 
                                    id="vsf-time-subscription" 
                                    value="<?php echo get_option('vsf-time-subscription'); ?>" />
                            
                            <br><br>
                            
                            <h3>Credenciales de integración con <a target="_blank" href="https://www.flow.cl/">Flow</a></h3>
                            <label style="width: 130px; display: inline-block;" for="vsf-apikey">ApiKey:&nbsp;</label>
                            <input  type="text" 
                                    name="vsf-apikey" 
                                    id="vsf-apikey" 
                                    value="<?php echo get_option('vsf-apikey'); ?>" />
                        
                            <br><br>
                            <label style="width: 130px; display: inline-block;" for="vsf-apisecret">ApiSecret:&nbsp;</label>
                            <input  type="text" 
                                    name="vsf-apisecret" 
                                    id="vsf-apisecret" 
                                    value="<?php echo get_option('vsf-apisecret'); ?>" />



                         
                            <?php submit_button(); ?>
                        </form>
                    </div>
                    
                </div>
            </div>
            
        <?php
    }

     /*
    * Función que registra las opciones del formulario en una lista blanca para que puedan ser guardadas
    */
    function vsf_save_settings(){
        register_setting('vsf-settings-group',
                        'vsf-time-subscription',
                        'number');
        register_setting('vsf-settings-group',
                        'vsf-apikey',
                        'text');
        register_setting('vsf-settings-group',
                        'vsf-apisecret',
                        'text');
    }
    add_action('admin_init','vsf_save_settings');

function vsf_send( $apiKey,$secretKey, $params) {
    $url = 'https://www.flow.cl/api/payment/getStatusByFlowOrder';
    $params = array("apiKey" => $apiKey) + $params;
    $params["s"] = vsf_sign($params,$secretKey);
    $response = vsf_httpGet($url, $params);
    
    if(isset($response["info"])) {
        $code = $response["info"]["http_code"];
        if (!in_array($code, array("200", "400", "401"))) {
            throw new Exception("Unexpected error occurred. HTTP_CODE: " .$code , $code);
        }
    }
    $body = json_decode($response["output"], true);
    return $body;
}

function vsf_httpGet($url, $params) {
    $url = $url . "?" . http_build_query($params);
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
    $output = curl_exec($ch);
    if($output === false) {
        $error = curl_error($ch);
        throw new Exception($error, 1);
    }
    $info = curl_getinfo($ch);
    curl_close($ch);
    return array("output" =>$output, "info" => $info);
}

function vsf_sign($params,$secretKey) {
    $keys = array_keys($params);
    sort($keys);
    $toSign = "";
    foreach ($keys as $key) {
        $toSign .= $key . $params[$key];
    }
    if(!function_exists("hash_hmac")) {
        throw new Exception("function hash_hmac not exist", 1);
    }
    return hash_hmac('sha256', $toSign , $secretKey);
}


if (!function_exists('base_url')) {
    function base_url($atRoot=FALSE, $atCore=FALSE, $parse=FALSE){
        if (isset($_SERVER['HTTP_HOST'])) {
            $http = isset($_SERVER['HTTPS']) && strtolower($_SERVER['HTTPS']) !== 'off' ? 'https' : 'http';
            $hostname = $_SERVER['HTTP_HOST'];
            $dir =  str_replace(basename($_SERVER['SCRIPT_NAME']), '', $_SERVER['SCRIPT_NAME']);

            $core = preg_split('@/@', str_replace($_SERVER['DOCUMENT_ROOT'], '', realpath(dirname(__FILE__))), NULL, PREG_SPLIT_NO_EMPTY);
            $core = $core[0];

            $tmplt = $atRoot ? ($atCore ? "%s://%s/%s/" : "%s://%s/") : ($atCore ? "%s://%s/%s/" : "%s://%s%s");
            $end = $atRoot ? ($atCore ? $core : $hostname) : ($atCore ? $core : $dir);
            $base_url = sprintf( $tmplt, $http, $hostname, $end );
        }
        else $base_url = 'http://localhost/';

        if ($parse) {
            $base_url = parse_url($base_url);
            if (isset($base_url['path'])) if ($base_url['path'] == '/') $base_url['path'] = '';
        }

        return $base_url;
    }
}

        add_action('init','prefix_check_short_code');

        function prefix_check_short_code() {

            if (!session_id()) {
                session_start();
            }

            $granted_access=true;
            $response_valid_pay_in_flow=null;    
            // Test if string contains the word 
            $in_fronted=!(strpos(strtolower(base_url(false,false,true)['path']), 'wp-admin') !== false);
            //$current_user = wp_get_current_user();
            $is_subscriber=( in_array( 'subscriber', (array) wp_get_current_user()->roles ) );
            //si es un subscriptor que intenta ver las paginas y tiene su pago vencido, se redirige al perfil para que
            // actualice su numero de orden de pago
            if($in_fronted && $is_subscriber){
                    $page_redirect_by_invalid_pay_subscription='/websitedomesa/wp-admin/profile.php';
                    // en primer lugar se revisa la session
                    unset($_SESSION['granted_access']);
                    unset($_SESSION['granted_access_reason']);
                    if(isset( $_SESSION['granted_access']) && !$_SESSION['granted_access']){
                        
                        header('Location: '.$page_redirect_by_invalid_pay_subscription);
                        die();
                    }else if(!isset( $_SESSION['granted_access'])){
                        // se obtiene el numero de la orden de pago del perfil
                        $number_order_pay_subscription_saved_profile= get_user_meta(wp_get_current_user()->ID, 'number_order_flow');
                        
                        if(count($number_order_pay_subscription_saved_profile)>0){
                            $number_order_pay_subscription_saved_profile=$number_order_pay_subscription_saved_profile[0];

                            $result_validate=validate_payment_subscription($number_order_pay_subscription_saved_profile,wp_get_current_user()->data->user_email);

                            $_SESSION['granted_access_reason']=$result_validate['granted_access_reason'];
                            $_SESSION['granted_access']= $granted_access=$result_validate['granted_access'];
                            //$response_valid_pay_in_flow['payer']/"leacido@hotmail.com"
                            
                        }else{
                            $_SESSION['granted_access_reason']='Numero de orden de pago faltante.';
                            $_SESSION['granted_access']= $granted_access=false;
                        }   
                        
                        if(!$granted_access){
                            //si es un subscriptor con el pago vencido, no dejarlo ver ninguna pagina
                            header('Location: '.$page_redirect_by_invalid_pay_subscription);
                            die();
                        }
       
                    }
            
             // si es un subscriber que esta en el backend
            }else if(!$in_fronted && $is_subscriber){
                unset($_SESSION['granted_access']);
                unset($_SESSION['granted_access_reason']);
            }
            

            /*
            ?>
            <script> console.log(<?php echo json_encode($response_valid_pay_in_flow)?>);</script>
            <?php
            */
        } 
    
        // validate_payment_subscription($number_order_pay_subscription_saved_profile,wp_get_current_user()->data->user_email)
    function validate_payment_subscription($number_order_pay,$user_email){

        $granted_access=true;
        $granted_access_reason=true;

        $apiKey=get_option('vsf-apikey');
        $secretKey=get_option('vsf-apisecret');

        //$response_valid_pay_in_flow=vsf_send( "2AFC320E-C7D5-4EF4-9EE2-81050C0CL3D4","e0b07350f768cc64cdad997d81e13e4e2e313a7e", array(
        $response_valid_pay_in_flow=vsf_send( $apiKey,$secretKey,
         array(    
            "flowOrder"=>$number_order_pay
        ));

        if(isset($response_valid_pay_in_flow['paymentData']['date'])){
            if(strtolower(trim($response_valid_pay_in_flow['payer']))!=strtolower(trim($user_email))){
                $granted_access_reason='Orden de pago invalida.';
                $granted_access=false;
            }else{

                $timeSubscription=get_option('vsf-time-subscription');
                if(!empty($timeSubscription) && is_numeric($timeSubscription)){
                    $date_limit_subscription=explode(" ",$response_valid_pay_in_flow['paymentData']['date'])[0];
                    $date_limit_subscription = new DateTime($date_limit_subscription);
    
                    date_add($date_limit_subscription, date_interval_create_from_date_string('60 days'));
                    $date_current = new DateTime(date_format(new DateTime(), 'Y-m-d'));
    
                    if ($date_limit_subscription < $date_current) {
                       $granted_access_reason='Orden de pago actual expirada.';
                       $granted_access=false;
                    }
                }
            }
        }else{
            $granted_access_reason='No se ha podido comprobar el estatus de su orden de pago actual.';
            $granted_access=false;
        }

        return array(
            'granted_access_reason'=> $granted_access_reason,
            'granted_access'=> $granted_access
        );
    }


    //Añadir campos adicionales al registro del usuario
    function campos_adicionales_registro_usuario(){
        $number_order_flow = (isset($_POST['number_order_flow'])) ? $_POST['number_order_flow'] : ''; ?>
        <p>
            <label for="number_order_flow">Nº Orden de pago<br/>
            <input type="number" id="number_order_flow" name="number_order_flow" class="input" size="25" value="<?php echo esc_attr($number_order_flow);?>"></label>
        </p>
    <?php }
    add_action('register_form', 'campos_adicionales_registro_usuario', 10,3);

    //Validar campos adicionales del registro de usuario
    function validar_datos_usuario($errors, $sanitized_user_login, $user_email){
        if(empty($_POST['number_order_flow'])){
         $errors->add('number_order_flow_error', __('<strong>ERROR</strong>: Por favor, introduzca Nº Orden de pago'));
        }else{
            $result_validate=validate_payment_subscription(addslashes(trim($_POST['number_order_flow'])),$user_email);
            if(!$result_validate['granted_access']){
                $errors->add('number_order_flow_error', __('<strong>ERROR</strong>: '.$result_validate['granted_access_reason']));
            }
        }
        return $errors;
    }
    add_filter('registration_errors', 'validar_datos_usuario', 10, 3);


    //Guardar los campos adicionales del usuario
    function guardar_campos_adicionales_usuario($user_id){
        if(isset($_POST['number_order_flow'])){
        update_user_meta($user_id, 'number_order_flow', sanitize_text_field($_POST['number_order_flow']));
        }
    }
    add_action('user_register', 'guardar_campos_adicionales_usuario');

    //Agregar los campos adicionales a Tu Perfil y Editar Usuario
    function agregar_campos_personalizados_usuario_backend($user) {
        $number_order_flow = esc_attr(get_the_author_meta('number_order_flow', $user->ID ));?>

            <table class="form-table">
            <tbody>

            <tr >
                <th>
                    <label for="number_order_flow">Nº Orden de pago</label>
                </th>
                <td>
                    <label>
                        <input type="text" name="number_order_flow" id="number_order_flow" class="regular-text" value="<?php echo $number_order_flow;?>" />
                        <br><span style="color:red"><?php
                            if(isset($_SESSION['granted_access_reason'])){
                                echo $_SESSION['granted_access_reason'];
                            }
                        ?></span>
                        <br><br>
                        <a class="button button-primary" href='https://www.flow.cl/btn.php?token=5f3a8rj' target='_self'>
                            Pagar subscripción
                        </a>

                    </label>
                </td>
            </tr>

            </tbody></table>
    <?php }
    add_action('show_user_profile', 'agregar_campos_personalizados_usuario_backend');
    add_action('edit_user_profile', 'agregar_campos_personalizados_usuario_backend');
    
    add_action('personal_options_update', 'guardar_campos_adicionales_usuario');
    add_action('edit_user_profile_update', 'guardar_campos_adicionales_usuario');



 

?>