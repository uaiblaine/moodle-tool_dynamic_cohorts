# H: drop the per-rule access check in delete_rules().
s{\n            rule_access::require_manage\(\$context\);\n}{\n}s;
