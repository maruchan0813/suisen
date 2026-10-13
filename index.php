<?php

// XAMPP の htdocs の中などに、このフォルダごと置いたときの入口。画面（public/）へ移すだけ。
// Apache の公開フォルダを public/ にしている場合は、このファイルは使われない。

header('Location: public/', true, 302);
