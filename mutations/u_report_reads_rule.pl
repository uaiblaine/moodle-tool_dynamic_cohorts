# U: decide the report on the context it was created in.
s{return rule_access::can_manage\(rule_access::context_for\(\$rule\)\);}{return rule_access::can_manage(\$this->get_context());}s;
