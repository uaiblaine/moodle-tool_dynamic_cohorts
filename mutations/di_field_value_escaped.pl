# DI: A typed field value reaches the description raw.
s{\$fieldvaluetext \= is_scalar\(\$fieldvalue\) \? s\(\(string\) \$fieldvalue\) \: \'\'\;}{\$fieldvaluetext = (string) \$fieldvalue;}s;
