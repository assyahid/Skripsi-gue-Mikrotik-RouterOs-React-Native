<?php
$serverName = "DESKTOP-PO8DHNG\SQLEXPRESS"; //serverName\instanceName
 
// Since UID and PWD are not specified in the $connectionInfo array,
// The connection will be attempted using Windows Authentication.
$connectionInfo = array( "Database"=>"otomax");
$conn = sqlsrv_connect( $serverName, $connectionInfo);

//$sql = "SELECT * FROM dbo.fisik";
//$query = sqlsrv_query($conn, $sql);
//if ($query === false)
//{  exit("<pre>".print_r(sqlsrv_errors(), true));
//}while ($row  = sqlsrv_fetch_array($query))
//{  
// echo "$row[sn]";
// echo "$row[vn]";
// echo "$row[kode_produk]";
// echo "$row[vn]";
//}
//sqlsrv_free_stmt($query);
?>