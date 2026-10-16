<?php echo json_encode(['remote'=>$_SERVER['REMOTE_ADDR']??null,'cf'=>$_SERVER['HTTP_CF_CONNECTING_IP']??null]);
