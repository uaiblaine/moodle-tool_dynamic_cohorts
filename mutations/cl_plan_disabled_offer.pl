# CL: the learning plan condition is offered while competencies are disabled.
s#return !get_config\('core_competency', 'enabled'\);#return false;#s;
