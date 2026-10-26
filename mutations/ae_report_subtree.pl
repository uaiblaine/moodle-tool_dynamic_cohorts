# AE: never scope the report below the system level.
s{if \(\$context->contextlevel != CONTEXT_SYSTEM\) \{\n            \[\$idparam}{if (false) \{\n            [\$idparam}s;
