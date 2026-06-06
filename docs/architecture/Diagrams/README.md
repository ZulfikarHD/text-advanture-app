# Diagrams

Mermaid diagrams, grouped by subject. Filenames use `Title_Case`.

## Subjects

| Folder | Contains |
|--------|----------|
| `Agents/` | Context isolation between Narrator / NPC / Player; NPC context assembly pipeline |
| `Engine/` | Session state machine, narrator turn loop, recorder pipeline, LLM client flow |
| `Data/` | Persistence ERD (two-realm: authoring vs save) |
| `Authoring/` | Authoring-realm flows (story CRUD, chapter management, etc.) |
| `App/` | Application shell & auth (sign-in / sign-out / route protection) |

## Index

| Diagram | File |
|---------|------|
| Agent context isolation | [Agents/Context_Isolation.md](./Agents/Context_Isolation.md) |
| Session state machine | [Engine/Session_State_Machine.md](./Engine/Session_State_Machine.md) |
| LLM client flow (role → client → log) | [Engine/Llm_Client_Flow.md](./Engine/Llm_Client_Flow.md) |
| Review gate flow (propose → decide) | [Engine/Review_Gate_Flow.md](./Engine/Review_Gate_Flow.md) |
| Persistence ERD (draft) | [Data/Persistence_Erd.md](./Data/Persistence_Erd.md) |
| Auth sign-in & route protection | [App/Auth_Signin_Flow.md](./App/Auth_Signin_Flow.md) |
| Account ownership & isolation | [App/Account_Ownership_Isolation.md](./App/Account_Ownership_Isolation.md) |
| App shell & navigation | [App/App_Shell_Navigation.md](./App/App_Shell_Navigation.md) |
| Story CRUD flow | [Authoring/Story_Crud_Flow.md](./Authoring/Story_Crud_Flow.md) |
| Story settings & overview flow | [Authoring/Story_Settings_Overview_Flow.md](./Authoring/Story_Settings_Overview_Flow.md) |
| Story workspace shell (tabs + placeholders) | [Authoring/Story_Workspace_Shell.md](./Authoring/Story_Workspace_Shell.md) |

> Keep diagrams free of explicit colors/styling so they render correctly in light and dark mode.
