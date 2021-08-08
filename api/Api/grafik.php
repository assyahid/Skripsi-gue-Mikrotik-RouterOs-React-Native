<?php
session_start();
error_reporting(0);
include_once('../inc/config.php');
include_once('../lib/fungsi.php');
include_once('../lib/routeros_api.class.php');
include_once('../inc/lang/id.php');
include_once('../inc/ip_mk/'.$_ROUTER.'.php');
if(empty($_SESSION['username'])) {
_e('<script>window.location.replace("./?index=login");</script>');
}
?>
<?php
$interface = $_GET["interface"];
$API = new RouterosAPI();
$API->debug = false;
$KONEK = $API->connect($_IPMK, $_POMK, $_USMK, _de(ltrim($_PSMK, __AHA)));
	if ($KONEK) {
		$rows = array(); 
		$rows2 = array();	
		
		   $API->write("/interface/monitor-traffic",false);
		   $API->write("=interface=".$interface,false);  
		   $API->write("=once=",true);
		   $READ = $API->read(false);
		   $ARRAY = $API->parseResponse($READ);
			if(count($ARRAY)>0){  
				$rx = number_format($ARRAY[0]["rx-bits-per-second"]/1024,1);
				$tx = number_format($ARRAY[0]["tx-bits-per-second"]/1024,1);
				$rows['name'] = 'Tx';
				$rows['data'][] = $tx;
				$rows2['name'] = 'Rx';
				$rows2['data'][] = $rx;
			}else{  
				echo $ARRAY['!trap'][0]['message'];	 
			} 
	}else{
		echo "<font color='#ff0000'>La conexion ha fallado. Verifique si el Api esta activo.</font>";
	}
	$API->disconnect();

	$result = array();
	array_push($result,$rows);
	array_push($result,$rows2);
	print json_encode($result, JSON_NUMERIC_CHECK);

?>
