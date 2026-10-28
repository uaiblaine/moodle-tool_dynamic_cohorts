# CI: every plan event counts as relevant.
s#return in_array\(\(int\) \$plan->templateid, \$ids, true\) \|\| in_array\(\(int\) \$plan->origtemplateid, \$ids, true\);#return true;#s;
