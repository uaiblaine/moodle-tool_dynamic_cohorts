# R: keep cohorts the user may not manage.
s{if \(!\$inside \|\| !rule_access::can_manage\(\$context\)\) \{}{if (!\$inside) \{}s;
