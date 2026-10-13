<?php
// 学生・コース・先輩を比べる13項目。
// 項目を変えるときはここだけを直す（DB の JSON、LLM の JSON スキーマ、計算はここから作られる）。
// data/courses.csv と data/seniors_test.csv の列もこのキーに合わせる。

return [
    ['key' => 'mechanics',            'label' => '力学・材料',           'group' => 'interest'],
    ['key' => 'thermo_fluid',         'label' => '熱・流体',             'group' => 'interest'],
    ['key' => 'design_making',        'label' => '設計・ものづくり',     'group' => 'interest'],
    ['key' => 'control_robotics',     'label' => '制御・ロボット',       'group' => 'interest'],
    ['key' => 'circuits_electronics', 'label' => '回路・電子',           'group' => 'interest'],
    ['key' => 'power_machines',       'label' => '電力・電気機器',       'group' => 'interest'],
    ['key' => 'programming',          'label' => 'プログラミング・情報', 'group' => 'interest'],
    ['key' => 'chemistry',            'label' => '化学',                 'group' => 'interest'],
    ['key' => 'biology_food',         'label' => '生物・食品',           'group' => 'interest'],
    ['key' => 'hands_on',             'label' => '手を動かす',           'group' => 'style'],
    ['key' => 'coding',               'label' => 'コード',               'group' => 'style'],
    ['key' => 'theory',               'label' => '理論',                 'group' => 'style'],
    ['key' => 'experiment',           'label' => '実験',                 'group' => 'style'],
];
