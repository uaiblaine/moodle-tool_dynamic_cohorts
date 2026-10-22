# W: drop the condition-form fragment's check.
s{\n    \$context = rule_access::context_from_id\(\(int\) \$args->context->id\);\n    rule_access::require_manage\(\$context\);\n}{\n    \$context = rule_access::context_from_id((int) \$args->context->id);\n}s;
