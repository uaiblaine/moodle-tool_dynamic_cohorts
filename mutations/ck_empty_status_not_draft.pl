# CK: an empty status choice is read as the draft status 0.
s#fn\(\$id\) => is_int\(\$id\) \|\| \(is_string\(\$id\) && ctype_digit\(\$id\)\)#fn(\$id) => true#s;
