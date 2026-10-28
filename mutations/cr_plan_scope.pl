# CR: a learning plan template of any context passes the category check.
s#return \$usable == count\(\$ids\) \? \[\] : \[.templateids. => get_string\(.outofscope., .tool_dynamic_cohorts.\)\];#return [];#s;
