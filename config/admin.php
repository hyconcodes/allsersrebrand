<?php

return [
    'emails' => array_map('trim', explode(',', env('ADMIN_EMAILS', 'hello@allsers.com,support@allsers.com'))),
];
