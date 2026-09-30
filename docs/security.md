# Security model

Ticket Operations does not grant requesters new access, bypass entity isolation, expose all users globally or replace GLPI business rules.

Version 0.0.1 exposes no ticket endpoint and performs no ticket mutation. Its only persistent changes are native GLPI profile-right rows.

Future controllers must reload every referenced object, validate plugin and native rights server-side, apply method and CSRF constraints, and return non-disclosing errors. Browser state is input, never authorization. Generic CRUD, massive actions and API exposure are not enabled.

