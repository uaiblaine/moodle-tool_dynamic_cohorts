# BN: a refused rule save commits whatever was written before the refusal.
s{\$transaction->rollback\(\$exception\);}{\$transaction->allow_commit();\n            throw \$exception;}s;
