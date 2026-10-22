# L, M: decide the rule's service in the system context.
s{\$context = rule_access::context_for\(\$rule\);}{\$context = \\core\\context\\system::instance();}s;
