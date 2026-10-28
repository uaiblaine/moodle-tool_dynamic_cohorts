# CQ: a competency condition is not checked again while the rule is processed.
s#(function rechecks_scope_when_processed\(\): bool \{\n        return )true;#${1}false;#s;
