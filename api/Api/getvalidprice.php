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

$API = new RouterosAPI();
$API->debug = false;
$KONEK_MK=$API->connect($_IPMK, $_POMK, $_USMK, _de(ltrim($_PSMK, __AHA)));

$uprofname = $_GET['name'];
if($uprofname != ""){
  $getprofile = $API->comm("/ip/hotspot/user/profile/print", array("?name" => "$uprofname"));
  $ponlogin = $getprofile[0]['on-login'];
  $getvalid = explode(",",$ponlogin)[3];
  $getperiod = explode(",",$ponlogin)[4];
  $getprice = explode(",",$ponlogin)[2];
  $gettime = explode(",",$ponlogin)[5];
  $getlock = explode(",",$ponlogin)[6];
  if($getlock!==" "){
  	$lockUs = "<tr><td>Kunci User </td><td>: ".$getlock."</td></tr>";
  }
  
  if(empty($getvalid)){
  $validasi = "<tr><td width='35%'>Masa Tenggang </td><td>: Unlimited</td></tr>";
  $validasix = "<tr><td width='35%'>Masa Aktif </td><td>: Unlimited</td></tr>";
  }else{
  $validasi = "<tr><td width='35%'>Masa Tenggang </td><td>: ".$getvalid."</td></tr>";
  $validasix = "<tr><td width='35%'>Masa Aktif </td><td>: ".$getvalid."</td></tr>";
  }
  if(empty($gettime)){
  if(empty($gettime)){
	  $timelim = $validasix;
	  }else{
  $timelim = "<tr><td width='35%'>Masa Aktif </td><td>: Unlimited</td></tr>";
  $timelimx = "<tr><td width='35%'><input hidden class='form-control' type='text' autocomplete='off' name='timelimit' value=''></tr>";
	  }
  }else{
  $timelim = "<tr><td width='35%'>Masa Aktif </td><td>: ".$gettime."</td></tr>";
  $timelimx = "<tr><td width='35%'><input hidden class='form-control' type='text' autocomplete='off' name='timelimit' value='".$gettime."'></tr>";
  }
  if(!empty($getperiod)){
  $tenggang = "<tr><td width='35%'>Masa Hapus </td><td>: ".$getperiod."</td></tr>";
  }
  if($getprice == 0){
  }else{
  	if($_LANG == "id"){
       $price = "<tr><td width='35%'>Harga </td><td>: ".$curency." ".number_format($getprice,0,",",".")."</td></tr>";
     }else{
    $price = "<tr><td width='35%'>Harga </td><td>: ".$curency." ".$curency." ".number_format($getprice)."</td></tr>";
  }
  }
  echo '
  <table width="100%">
  '.$timelim.''.$validasi.''.$tenggang.''.$price.''.$lockUs.'
  </table>';
  echo $timelimx;
}
?>