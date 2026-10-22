# AA: read the edited rule with new rule(), which reports a missing id before the access check.
s{rule_access::context_for\(rule::get_record\(\['id' => \(int\) \$formdata->id\]\) \?: null\)}{(new rule((int) \$formdata->id))->get_context()}s;
