<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Headers: token"); //Add this line to receive api token
header("Access-Control-Allow-Headers: idEmpresa"); //Add this line to receive api token
$headers = apache_request_headers();
$token = $headers['token'];
$idEmpresa = $headers['idEmpresa'];

$mode = $_GET['mode'];
include_once '../config/Database.php';
include("../../../util/system/conexion.php");
include_once '../class/Items.php';
include("../../siglo21/classWoo.php");


function isValidToken($token, $dbtoken){
    if(hash_equals($token, $dbtoken)){ 
        return true; 
    }else{ 
        return false; 
    } 
}
$conexion = new Conexion('../');

$conexion->conectar();

$query = "SELECT * FROM cloudapitokens WHERE apitoken = ?";
$tipos = "i";
$parametros = [$token];
$result = $conexion->consulta_preparada($query, $tipos, $parametros);
$dbtoken = 0;
$dbmode = '';
if(isset($result->apitoken)){
    $dbtoken = $result->apitoken;
    $dbmode = $result->mode;
}else{
    http_response_code(404);
    echo json_encode(array("message" => "Not Found. Debe crear un Token en la plataforma antes de intentar conectarse. Para mas informacion contacte a uno de nuestros asesores"));
    exit;
}

if(!isValidToken($token, $dbtoken)){
    http_response_code(401);
    echo json_encode(array("message" => "Unauthorized. Invalid Token."));
    exit;
}

if($idEmpresa){
    //obtenemos los datos de la empresa para conectarnos a woocomerce
    $queryEmpresas = "SELECT * FROM empresas_woo WHERE id = ? limit 1" ;
    $tipos = "i";
    $parametrosEmpresas = [$idEmpresa]; //reemplazar por empresa que venga en el header
    $resultados = $conexion->consulta_preparada($queryEmpresas, $tipos, $parametrosEmpresas);
    //var_dump($resultados);
    //aqui agregar en base de datos los marketplaces para obtener sus credenciales desde la base de datos
    if(isset($resultados->url) && isset($resultados->clavecliente) && isset($resultados->clavesecreta)){
        $url = $resultados->url;
        $clavecliente = $resultados->clavecliente;
        $clavesecreta = $resultados->clavesecreta;
    }else{
        http_response_code(204);
        echo json_encode(array("message" => "No Content. Por favor verifica que los siguientes datos haya sido compartidos con nosotros [URL, CLAVE CLIENTE, CLAVE SECRETA] del Marketplace al que deseas conectarte."));
        exit;
    }
}else{
    http_response_code(401);
    echo json_encode(array("message" => "Unauthorized. La empresa a la que desea conectarse no existe."));
    exit;
}


$conexionWoo = new woo($url,$clavecliente,$clavesecreta);
$conexionWoo->connect();
$response = array();
if(isset($mode)){
    switch ($mode) {
        case 'brands':
            $getBrand = $conexionWoo->listAtributos();
            if(count($getBrand) > 0){
                $getBrand = json_decode(json_encode($getBrand),true);
                
                $brand_id = 0;
                foreach($getBrand as $brand){
                    if($brand['slug'] == 'pa_brand'){
                        $brand_id = $brand['id'];
                    }
                }
                if($brand_id != 0){
                    $getBrands = $conexionWoo->listAtributosByTerms($brand_id);
                    $getBrands = json_decode(json_encode($getBrands),true);
                    $response['status'] = $getBrands;
                }else{
                    $response['status'] = 'No existe el atributo pa_brands en el comercio';
                }
            }else{
                $response['status'] = 'No se obtuvieron Brands del comercio';
            }
            break;
        case 'categorias':
            $getCategories = $conexionWoo->listCategorias();
            if(count($getCategories) > 0){
                $getCategories = json_decode(json_encode($getCategories),true);
                $response['status'] = $getCategories;
            }else{
                $response['status'] = 'No se obtuvieron Categorias del comercio';
            }
            break;
        default:
            # code...
            break;
    }
}else{
    http_response_code(401);
    echo json_encode(array("message" => "Llene el parámetro mode y vuelva a intentarlo"));
    exit;
}


if($response){
    http_response_code(201);
    echo json_encode(array("message" => $response));
}else{
    http_response_code(503);
    echo json_encode(array("message" => "No se ha recibido MODE"));
}
