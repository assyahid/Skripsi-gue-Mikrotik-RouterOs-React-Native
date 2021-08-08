
<?php

//=====================================================START====================//

/*
 *  Base Code   : radya modded by BangAchil
 *  Email       : kesumaerlangga@gmail.com
 *  Telegram    : @bangachil
 *
 *  Name        : Mikrotik bot telegram - php
 *  Function    : Mikortik api
 *  Manufacture : November 2018
 *  Last Edited : 26 Desember 2019
 *
 *  Please do not change this code
 *  All damage caused by editing we will not be responsible please think carefully,
 *
 */

//=====================================================START SCRIPT====================//
    session_start();

    if (!isset($_SESSION["Mikbotamuser"])) {
        header("Location:admin/login.php");
    } else {
      echo '<script>';
      echo 'window.location.replace("../pages/index.php");';
      echo '</script>';

       
    }

?>

