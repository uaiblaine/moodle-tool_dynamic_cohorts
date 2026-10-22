# T: drop the condition service's access check.
s{\n        rule_access::require_manage\(\$context\);\n}{\n}s;
