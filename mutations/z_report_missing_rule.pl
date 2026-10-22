# Z: load the report's rule with MUST_EXIST, before can_view() runs.
s{\$rule = rule::get_record\(\['id' => \$ruleid\]\) \?: null;}{\$rule = rule::get_record(['id' => \$ruleid], MUST_EXIST);}s;
