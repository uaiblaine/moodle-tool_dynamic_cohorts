# EF: A TypeError from a condition's SQL escapes while listing a rule's users.
s{\$sqldata \= condition_manager\:\:build_sql_data\(\$conditions\, \$rule\-\>get\(\'operator\'\)\, \$userid\)\;\n        \} catch \(\\Throwable \$exception\) \{}{\$sqldata = condition_manager::build_sql_data(\$conditions, \$rule->get('operator'), \$userid);\n        \} catch (\\Exception \$exception) \{}s;
