# B: drop the discovered-class check.
s{\n        if \(!array_key_exists\(\$classname, self::get_all_conditions\(false\)\)\) \{\n.*?\n        \}\n}{\n}s;
