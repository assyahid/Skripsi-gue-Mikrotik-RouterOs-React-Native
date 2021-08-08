<?php
session_start();
error_reporting(0);
include_once('../inc/config.php');
include_once('../lib/fungsi.php');
include_once('../lib/routeros_api.class.php');
include_once('../inc/lang/id.php');
include_once('../inc/ip_mk/'.$_ROUTER.'.php');
?>
<?php
$user = $_GET["id"];
$API = new RouterosAPI();
$API->debug = false;
$KONEK = $API->connect($_IPMK, $_POMK, $_USMK, _de(ltrim($_PSMK, __AHA)));
	if ($KONEK) {
		$rows = array(); 
		$rows2 = array();	
		
			$ARRAY = $API->comm("/ip/hotspot/active/print", array("?.id"=> "$user",));
			if(count($ARRAY)>0){  
				$tx = number_format($ARRAY[0]["packets-in"]/1024,0);
				$rx = number_format($ARRAY[0]["packets-out"]/1024,0);
				$tx1 =str_replace(',', '', $tx);
				$rx1 =str_replace(',', '', $rx);
				$rows['name'] = 'IN';
				$rows['data'][] = $tx1;
				$rows2['name'] = 'OUT';
				$rows2['data'][] = $rx1;
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
