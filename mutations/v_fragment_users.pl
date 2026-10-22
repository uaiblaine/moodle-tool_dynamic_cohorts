# V: drop the matching-users fragment's own check.
s{\n    rule_access::require_manage\(\$context\);\n}{\n}s;
