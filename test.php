<?php

$hash = "$2y$10$Ze7RgOScBRdFW/Ar2nPBZO1mgS8XY9zf5SriaRiomnX..";

var_dump(password_verify("123456", $hash));

exit;