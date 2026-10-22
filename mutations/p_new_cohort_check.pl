# P: drop the check on the cohort a rule is pointed at.
s{\n        rule_access::require_manage\(rule_access::context_for_cohort\(\(int\) \(\$formdata->cohortid \?\? 0\)\)\);\n}{\n}s;
