<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Max-Age: 3600");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");
header("Access-Control-Allow-Headers: token"); //Add this line to receive api token
header("Access-Control-Allow-Headers: idEmpresa"); //Add this line to receive api token
 
include_once '../config/Database.php';
include("../../../util/system/conexion.php");
include_once '../class/Items.php';
include("../../siglo21/classWoo.php");

$headers = apache_request_headers();
$token = $headers['token'];
$idEmpresa = $headers['idEmpresa'];

$conexion = new Conexion('../');

$conexion->conectar();

function isValidToken($token, $dbtoken){
    if(hash_equals($token, $dbtoken)){ 
        return true; 
    }else{ 
        return false; 
    } 
}
function ganancia($precio,$ganancia){
    if(!empty($precio)){
        $total = $precio * $ganancia;
        return round($total,2);
    }else{
        return 0;
    }
}
$ganancia = 1.40;
$url = "";
$clavecliente = "";
$clavesecreta = "";
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
//var_dump( $url, $clavecliente, $clavesecreta); //comprobar data llena


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

 
/*$database = new Database();
$db = $database->getConnection();
 
$items = new Items($db);*/
 
$productosFeed = json_decode(file_get_contents("php://input"));

$respuesta_proceso = array();

$conexionWoo = new woo($url,$clavecliente,$clavesecreta);
$conexionWoo->connect();
$productosWoo = '';
$productosWoo = $conexionWoo->getProductsWoo();
$getBrand = $conexionWoo->listAtributos();

$ProductExistBySku = array();
            $brand_id=0;
            if(count($getBrand) > 0){
                $getBrand = json_decode(json_encode($getBrand),true);
                foreach($getBrand as $brand){
                    if($brand['slug'] == 'pa_brand'){
                        $brand_id = $brand['id'];
                    }
                }
            }
            if($brand_id == 0){
                $data = [
                    'name' => 'Brand',
                    'slug' => 'pa_brand',
                    'has_archives' => true
                ];
                $brandCreada = $conexionWoo->createAtributo($data);
                $brandCreada = json_decode(json_encode($brandCreada),true);
                $brand_id = $brandCreada['id'];
            }
            $imagesObject = array();
            $imagesObjectTemp = array();
            if($productosWoo){
                foreach($productosWoo as $producto){
                    if(isset($producto['sku']) && isset($producto['id'])){
                        $ProductExistBySku[$producto['sku']] = $producto['id']; 
                    } 
                    if(isset($producto['images'][0])) {
                        $imagesObject = json_decode(json_encode($producto['images'][0]), true);
                        
                        if(isset($imagesObject['id'])){
                            $imagesObjectTemp[$producto['sku']]['id_image'] = $imagesObject['id'];
                        }
                    }      
                }
            }else{
                FuncionesCron::EscribirLogs('CronJobs', "[Proceso Woo] Lista de Skus no se pudo armar $url");
                http_response_code(503);
                echo json_encode(array("message" => "Lista de Skus no se pudo armar"));
                
            }
            
foreach ($productosFeed as $producto) {
    if(!empty($producto->sku) && !empty($producto->name) 
    && !empty($producto->type) && !empty($producto->regular_price) 
    && !empty($producto->short_description) && !empty($producto->stock_quantity) 
    && !empty($producto->image) && !empty($producto->peso) 
    && !empty($producto->dimensiones) && !empty($producto->marca) 
    && !empty($producto->gravaIva)){
        $dimensions = get_object_vars($producto->dimensiones);
           
        if (array_key_exists($producto->sku, $ProductExistBySku)) {
            $respuesta_proceso['existentes'][] = "El producto con sku".$producto->sku." ya existe";
        }else{
                $image = $url.'wp-content/uploads/woocommerce-placeholder.png';
                if(isset($productoSiglo['urlImage']['url0'])){
                    $image = $productoSiglo['urlImage']['url0'];
                }
                if(isset($productoSiglo['marca'])){
                    $brand = $productoSiglo['marca'];
                }
                $data = [
                    'sku' => $producto->sku,
                    'name' => $producto->name,
                    'type' => $producto->type,
                    'regular_price' => strval(ganancia($producto->regular_price, $ganancia)),
                    'short_description' => $producto->short_description,
                    'stock_quantity' => $producto->stock_quantity,
                    'images' => [
                        [
                            'src' => $producto->image
                        ]
                    ],
                    'weight' => (string)$producto->peso,
                    'dimensions' => [
                        'length' => (string)$dimensions['profundidad'],
                        'width'  => (string)$dimensions['ancho'],
                        'height' => (string)$dimensions['alto'] 
                    ],
                    'attributes'=> [
                        [
                            "id" => $brand_id,
                            'name'=> 'Brand',
                            'visible' => true,
                            'options'=> array($producto->marca)
                        ]
                      ]
                ];
                $liveCreados = array();
                if (!array_key_exists($producto->sku, $liveCreados)) {
                    try {
                        $creado = $conexionWoo->createProduct($data);
                        $creado = json_decode(json_encode($creado,true),true);
                        
                        if(isset($creado['id'])){
                            $liveCreados[$producto->sku] = $creado['id'];
                            $respuesta_proceso['creados'][] = "Producto con sku: ".$producto->sku." creado con exito";
                            //FuncionesCron::EscribirLogs('Creados',"Creado: ".$productoSiglo['sku']);
                        }else{
                            $respuesta_proceso['Error'][] = 'El producto con sku: '.$productoSiglo['sku']." no se creo debido a un error del api de woocommerce";
                            //FuncionesCron::EscribirLogs('Creados',"Error Creado: ".$productoSiglo['sku']);
                        }
                        
                    } catch (Exception $e) {
                        $respuesta_proceso['Error'][] = 'El producto con sku:'.$productoSiglo['sku']." se intento crear pero no hubo acceso";
                        //echo "Exception at ".$productoSiglo['sku'];
                        //FuncionesCron::EscribirLogs('Creados',"Error Creado Exception: ".$productoSiglo['sku']. $e->getMessage());
                    }
                }else{
                    $respuesta_proceso['Duplicados'][] = 'El producto con sku:'.$productoSiglo['sku']." esta duplicado";
                    //FuncionesCron::EscribirLogs('Duplicados',"Error Creado: ".$productoSiglo['sku']);
                }
        }
        /*$items = new Items($db);

        $items->name = $producto->name;
        $items->description = $producto->description;
        $items->price = $producto->price;
        $items->category_id = $producto->category_id;    
        $items->brand_id = $producto->brand_id;
        $items->created = date('Y-m-d H:i:s'); 

        if($items->create()){
            http_response_code(201);
            echo json_encode(array("message" => "Item was created."));
        } else{
            http_response_code(503);
            echo json_encode(array("message" => "Unable to create item."));
        }*/
        
    }else{
        //http_response_code(400);
        $respuesta_proceso['format_error'][] = "Unable to create item. Data is incomplete";
        //echo json_encode(array("message" => "Unable to create item. Data is incomplete."));
    }
    
}
if($respuesta_proceso){
    http_response_code(201);
    echo json_encode(array("message" => $respuesta_proceso));
} else{
    http_response_code(503);
    echo json_encode(array("message" => "No se ha creado ningún producto"));
}
?>