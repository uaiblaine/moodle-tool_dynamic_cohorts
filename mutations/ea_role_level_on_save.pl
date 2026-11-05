# EA: A role condition with a context level get_sql() has no branch for is saved.
s{        if \(\!in_array\(\(int\) \(\$data\[\'contextlevel\'\] \?\? 0\)\, self\:\:CONTEXT_LEVELS\, true\)\) \{\n            \$errors\[\'contextlevel\'\] \= get_string\(\'required\'\)\;\n            return \$errors\;\n        \}\n\n}{}s;
