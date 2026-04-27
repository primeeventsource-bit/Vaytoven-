# Scripts

Repo-wide tooling: build helpers, smoke tests, deploy scripts.

Currently empty. Surface-specific build scripts live next to the surface (`app/build.js`, `admin/build.js`).

When something belongs here:
- It crosses surfaces (e.g., a script that triggers builds in both `app/` and `admin/`).
- It's repo-management tooling (e.g., a script that re-extracts SRS markdown from the docx).
- It's a deploy script that's the same for everything.

Anything that's specific to one surface lives next to that surface.
