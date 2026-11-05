# EG: A TypeError from a condition's SQL escapes while counting a rule's users.
s{\$sqldata \= condition_manager\:\:build_sql_data\(\$conditions\, \$rule\-\>get\(\'operator\'\)\)\;\n        \} catch \(\\Throwable \$exception\) \{}{\$sqldata = condition_manager::build_sql_data(\$conditions, \$rule->get('operator'));\n        \} catch (\\Exception \$exception) \{}s;
