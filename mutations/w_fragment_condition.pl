# W: drop the condition-form fragment's check.
s{\n    rule_access::require_manage\(rule_access::context_from_id\(\(int\) \$args->context->id\)\);\n}{\n}s;
