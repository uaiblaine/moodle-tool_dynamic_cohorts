# I: stop replacing the raw submitted id with the checked integer.
s{\n            \$submittedrecord\['id'\] = \$id;\n}{\n}s;
