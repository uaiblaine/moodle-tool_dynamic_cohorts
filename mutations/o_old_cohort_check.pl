# O: drop the check on the cohort a rule fills now.
s{\n            rule_access::require_manage\(rule_access::context_for\(rule::get_record\(\['id' => \(int\) \$formdata->id\]\) \?: null\)\);\n}{\n}s;
