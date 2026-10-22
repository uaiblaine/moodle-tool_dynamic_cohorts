# N: decide toggle_status in the system context (the 8-space line; delete_rules' loop is indented 12).
s{\n        \$context = rule_access::context_for\(\$rule\);\n}{\n        \$context = \\core\\context\\system::instance();\n}s;
