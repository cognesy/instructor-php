## PATTERN [Vertical Slice Structure]


```
Contexts/{FeatureName}/
├── {Name}Intent.swift       [enum + executor → routing]
├── {Name}State.swift        [@Published singleton → reactive state]
├── {Name}Config.swift       [UserDefaults wrapper → config]
├── {Name}Capability.swift   [actual work → API/timer/file/subprocess]
├── {Name}Panel.swift        [SwiftUI → UI]
├── {Name}Menu.swift         [static buildMenu() → NSMenuItem]
├── {Name}HTTP.swift         [static handle*() → JSON-RPC]
├── {Name}Hotkeys.swift      [static registerAll() → keyboard]
├── {Name}Errors.swift       [enum Error → typed errors]
├── {Name}Strings.swift      [static strings → i18n ready]
├── {Name}Lifecycle.swift    [setup()/teardown() → init/cleanup]
└── CLAUDE.md                [OUTCOME/PATTERN/CONSTRAINT/DEPENDENCY]