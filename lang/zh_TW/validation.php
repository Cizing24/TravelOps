<?php

return [
    'required' => ':attribute 為必填。',
    'integer' => ':attribute 必須是整數。',
    'between' => ['numeric' => ':attribute 須介於 :min 到 :max。'],
    'min' => ['numeric' => ':attribute 至少為 :min。', 'string' => ':attribute 至少 :min 字元。'],
    'max' => ['string' => ':attribute 最多 :max 字元。', 'numeric' => ':attribute 最多 :max。'],
    'email' => '請輸入有效的 Email。',
    'string' => ':attribute 必須是文字。',
    'regex' => ':attribute 格式不正確。',
    'in' => ':attribute 不在可選範圍。',
    'uuid' => ':attribute 必須是有效識別碼。',
    'unique' => ':attribute 已被使用。',
    'exists' => ':attribute 不存在。',
    'attributes' => [
        'quantity' => '購買數量', 'venue_id' => '場館', 'code' => '核銷代碼',
        'product_id' => '商品', 'idempotency_key' => '下單識別碼', 'scenario' => '情境',
        'email' => 'Email', 'password' => '密碼',
    ],
];
