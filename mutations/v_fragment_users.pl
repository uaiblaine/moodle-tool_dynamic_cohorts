# V: drop the matching-users fragment's own check.
s{\n    \$context = rule_access::context_for\(\$rule\);\n    rule_access::require_manage\(\$context\);\n}{\n    \$context = rule_access::context_for(\$rule);\n}s;
