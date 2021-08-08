
<?php


    session_start();

    if (!isset($_SESSION["mikrotikuser"])) {
        header("Location:admin/login.php");
    } else {
      echo '<script>';
      echo 'window.location.replace("../pages/index.php");';
      echo '</script>';

       
    }

?>

