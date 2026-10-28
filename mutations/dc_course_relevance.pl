# DC: one-course evidence from any course counts as relevant.
s#return \$coursecontext && \(int\) \$coursecontext->instanceid === \$this->get_int\(.courseid., 0\);#return true;#s;
