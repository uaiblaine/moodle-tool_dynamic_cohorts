# Y: resolve the form's rule with get_rule(), which reports a missing id before any access check.
s{return rule_access::context_for\(rule::get_record\(\['id' => \$ruleid\]\) \?: null\);}{return \$this->get_rule()->get_context();}s;
