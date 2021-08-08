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
$API->connect($_IPMK, $_POMK, $_USMK, _de(ltrim($_PSMK, __AHA)));

$pilbl = $_GET['pilbl'];
$pilhr = $_GET['pilhr'];
$getcomment = $_GET['comment'];
if($getcomment!=='all'){$getcomment1 = ' - '.$getcomment;}

if($pilbl){
	
$mikmosLoad = $API->comm("/system/script/print", array("?owner" => "$pilbl"));
$mikmosTot = count($mikmosLoad);

for ($i=0; $i<$mikmosTot; $i++){
$mikmosData = $mikmosLoad[$i];
$mikmoslits = explode("-|-",$mikmosData['name']);

if($getcomment==$mikmoslits[7]){
$bilHRz += $mikmoslits[3];
}elseif($getcomment=='all'){ 
$bilHRz += $mikmoslits[3];
}

}


// memanggil library FPDF
require('../lib/fpdf/fpdf.php');
// intance object dan memberikan pengaturan halaman PDF
$pdf = new FPDF('p','mm','A4');
// membuat halaman baru
$pdf->AddPage();
$pdf->SetFont('Arial','I',8);
$pdf->Cell(95,5,'Sistem Informasi Mikrotik',0,0,'L');
$pdf->Cell(95,5,'Download '.date("d-M-y h:m:s").'',0,0,'R');
$pdf->Cell(10,7,'',0,1);
// setting jenis font yang akan digunakan
$pdf->SetFont('Arial','B',16);
// mencetak string 
$pdf->Cell(190,7,'Billing Report '.$_RPER.'',0,1,'C');
$pdf->SetFont('Arial','B',12);
$pdf->Cell(190,7,'Penjualan Pada Bulan '.$pilbl.' '.$getcomment1.'',0,1,'C');

// Memberikan space kebawah agar tidak terlalu rapat
$pdf->Cell(10,7,'',0,1);

$pdf->SetFont('Arial','B',13);
$pdf->Cell(190,7,'Total Pendapatan: '.rupiah($bilHRz),1,1,'R');
$pdf->Cell(10,1,'',0,1);
$pdf->SetFont('Arial','B',10);
$pdf->Cell(10,6,'No',1,0);
$pdf->Cell(25,6,'Waktu',1,0);
$pdf->Cell(35,6,'Tanggal',1,0);
$pdf->Cell(75,6,'Voucher',1,0);
$pdf->Cell(45,6,'Penjualan',1,1);

$pdf->SetFont('Arial','',10);
$pdf->Cell(10,1,'',0,1);
$pdf->SetFont('Arial','',10);

for ($i=0; $i<$mikmosTot; $i++){

$regtable = $mikmosLoad[$i];
$getname = explode("-|-",$regtable['name']);
$getowner = $regtable['owner'];
$tgl = $getname[0];
$getdy = explode("/",$tgl);
$m = $getdy[0];
$dy = $m."/".$getdy[1]."/".$getdy[2];
$ltime = $getname[1];
$username = $getname[2];
$price = $getname[3];
$no = $i+1;
$pdf->Cell(10,6,$no,1,0);
$pdf->Cell(25,6,$ltime,1,0);
$pdf->Cell(35,6,$dy,1,0);
$pdf->Cell(75,6,$username,1,0);
$pdf->Cell(45,6,rupiah($price),1,1); 
}


$pdf->Output();


}






if($pilhr){
$mikmosLoad = $API->comm("/system/script/print", array(
 "?source" => "$pilhr"));
$mikmosTot = count($mikmosLoad);

for ($i=0; $i<$mikmosTot; $i++){
$mikmosData = $mikmosLoad[$i];
$mikmoslits = explode("-|-",$mikmosData['name']);
if($getcomment==$mikmoslits[7]){
$bilHRz += $mikmoslits[3];
}elseif($getcomment=='all'){ 
$bilHRz += $mikmoslits[3];
}
}

$tgl = $mikmosData[0];
$getdy = explode("/",$pilhr);
$dy = $getdy[1]." ".ucfirst($getdy[0])." ".$getdy[2];

// memanggil library FPDF
require('../lib/fpdf/fpdf.php');
// intance object dan memberikan pengaturan halaman PDF
$pdf = new FPDF('p','mm','A4');
// membuat halaman baru
$pdf->AddPage();
$pdf->SetFont('Arial','I',8);
$pdf->Cell(95,5,'Sistem Informasi Mikrotik',0,0,'L');
$pdf->Cell(95,5,'Di Download '.date("d-M-y h:m:s").'',0,0,'R');
$pdf->Cell(10,7,'',0,1);
// setting jenis font yang akan digunakan
$pdf->SetFont('Arial','B',16);
// mencetak string 
$pdf->Cell(190,7,''.strtoupper('Billing Report ' .$_RPER).'',0,1,'C');
$pdf->SetFont('Arial','B',12);
$pdf->Cell(190,7,'Pendapatan Pada '.$dy.' '.$getcomment1.'',0,1,'C');

// Memberikan space kebawah agar tidak terlalu rapat
$pdf->Cell(10,7,'',0,1);

$pdf->SetFont('Arial','B',13);
$pdf->Cell(190,7,'Total Pendapatan: '.rupiah($bilHRz),1,1,'R');
$pdf->Cell(10,1,'',0,1);
$pdf->SetFont('Arial','B',10);
$pdf->Cell(10,6,'No',1,0);
$pdf->Cell(25,6,'Waktu',1,0);
$pdf->Cell(35,6,'Tanggal',1,0);
$pdf->Cell(75,6,'Voucher',1,0);
$pdf->Cell(45,6,'Penjualan',1,1);

$pdf->SetFont('Arial','',10);
$pdf->Cell(10,1,'',0,1);
$pdf->SetFont('Arial','',10);

for ($i=0; $i<$mikmosTot; $i++){

$regtable = $mikmosLoad[$i];
$getname = explode("-|-",$regtable['name']);
$getowner = $regtable['owner'];
$tgl = $getname[0];
$getdy = explode("/",$tgl);
$m = $getdy[0];
$dy = $m."/".$getdy[1]."/".$getdy[2];
$ltime = $getname[1];
$username = $getname[2];
$price = $getname[3];
$no = $i+1;	
$pdf->Cell(10,6,$no,1,0);
$pdf->Cell(25,6,$ltime,1,0);
$pdf->Cell(35,6,$dy,1,0);
$pdf->Cell(75,6,$username,1,0);
$pdf->Cell(45,6,rupiah($price),1,1); 
}


$pdf->Output();

}



?>