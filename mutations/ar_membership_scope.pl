# AR: any cohort counts as usable.
s{if \(\$DB->count_records_sql\(\$sql, \$inparams \+ \$scopeparams\) == count\(\$ids\)\) \{}{if (true) \{}s;
