<?php
$message = "Message can contain html code like a <a href='https://redcap.vanderbilt.edu' target='_blank'>Link</a><br>";
$message .= "Types of status: <ul><li><strong>success</strong></li><li><strong>warning</strong></li><li><strong>danger</strong></li></ul>";

echo json_encode(array(
    'status' => 'success',
    'message' => $message
));
?>