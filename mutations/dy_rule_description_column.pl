# DY: The rules report prints a rule description raw.
s{            \-\>set_is_sortable\(false\)\n            \-\>add_callback\(static function \(\$value\)\: string \{\n                return format_string\(\(string\) \$value\, true\, \[\'context\' \=\> \\core\\context\\system\:\:instance\(\)\]\)\;\n            \}\)\;}{            ->set_is_sortable(false);}s;
