<?php


try {

    $conn = new PDO(
        "mysql:host=kodama.proxy.rlwy.net;port=27960;dbname=railway;charset=utf8mb4",
        "root",
        "GejrRXAHOyNbLyiCYtwmSpYssenQyJrV"
    );

    

    $stmt = $conn->query("SHOW TABLES");

    while($row = $stmt->fetch()) {

        echo $row[0] . "<br>";
    }

} catch(PDOException $e) {
    var_dump(PDO::getAvailableDrivers());
exit;

    echo $e->getMessage();
}