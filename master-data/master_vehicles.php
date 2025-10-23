<?php
header('Content-Type: application/json; charset=utf-8');
echo json_encode(['ok'=>true, 'endpoint'=>$_SERVER['REQUEST_URI']], JSON_UNESCAPED_UNICODE);
