<?php

header("Content-Type: application/json");

echo json_encode([
    "app" => "SoftyHealth API",
    "status" => "online"
]);