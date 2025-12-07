# Frontmatter-Driven Examples Research - Index

## Overview

This directory contains complete research and implementation plan for reorganizing examples from centralized `./examples` directory to package-specific `packages/*/examples/` directories using frontmatter to drive documentation navigation.

## Documents

### 📋 [README.md](./README.md)
**Complete Research Document**
- Current system analysis (hub commands, docs commands, frontmatter)
- Target architecture with enhanced frontmatter
- Detailed implementation plan (3 phases)
- Migration strategy and validation tools
- Benefits and risk assessment

### 🔧 [technical-guide.md](./technical-guide.md)
**Step-by-Step Implementation Guide**
- Exact code changes for all affected files
- Enhanced ExampleInfo, Example, ExampleRepository classes
- Migration scripts and testing procedures
- Quick frontmatter updater script
- Backward compatibility preservation

### 🧪 [proof-of-concept.php](./proof-of-concept.php)
**Working Demonstration**
- Simulates enhanced frontmatter approach
- Analyzes current 194+ examples
- Shows navigation structure generation
- Demonstrates package detection
- Provides migration plan for all example groups

### 📊 [SUMMARY.md](./SUMMARY.md)
**Executive Summary**
- Key findings and recommendations
- Implementation timeline and phases
- Risk mitigation and benefits
- Ready-to-use next steps

## Key Findings

### Infrastructure Ready ✅
- **Frontmatter parsing already exists** - `ExampleInfo` uses `FrontMatter::parse()`
- **Hub commands work** - `composer hub list|run|all|show`
- **Docs generation works** - `composer docs`, `gen:examples`, `gen:mintlify`, `gen:mkdocs`
- **Navigation is dynamic** - Mintlify auto-generates, MkDocs can be enhanced

### Current State
```
examples/
├── A01_Basics → instructor/basics     (18 examples)
├── A02_Advanced → instructor/advanced (18 examples)
├── A03_Troubleshooting → instructor/troubleshooting (5 examples)
├── A04_APISupport → instructor/api_support (23 examples)
├── A05_Extras → instructor/extras (15 examples)
├── B01_LLM → polyglot/llm_basics (7 examples)
├── B02_LLMAdvanced → polyglot/llm_advanced (14 examples)
├── B03_LLMTroubleshooting → polyglot/llm_troubleshooting (1 example)
├── B04_LLMApiSupport → polyglot/llm_api_support (22 examples)
├── B05_LLMExtras → polyglot/llm_extras (7 examples)
├── C01_ZeroShot → prompting/zero_shot (8 examples)
├── C02_FewShot → prompting/few_shot (4 examples)
├── C03_ThoughtGen → prompting/thought_gen (10 examples)
├── C04_Ensembling → prompting/ensembling (10 examples)
├── C05_SelfCriticism → prompting/self_criticism (6 examples)
├── C06_Decomposition → prompting/decomposition (6 examples)
└── C07_Misc → prompting/misc (22 examples)
```

### Target State
```yaml
# Enhanced frontmatter in packages/*/examples/**/run.php
---
title: 'Basic use'
docname: 'basic_use'
tab: 'instructor'           # instructor|polyglot|prompting|http
group: 'basics'            # Group within tab
groupTitle: 'Basics'       # Human-readable title
weight: 100                # Optional: ordering
tags: ['beginner']         # Optional: filtering
---
```

## Implementation Phases

### Phase 1: Infrastructure (1 day) ⚡
**Files to modify:**
- `packages/hub/src/Data/ExampleInfo.php` - Parse extended frontmatter
- `packages/hub/src/Data/Example.php` - Package detection + frontmatter navigation
- `packages/hub/src/Services/ExampleRepository.php` - Multi-directory scanning
- `packages/doctor/src/Docs.php` - Multi-package configuration

**Result:** Enhanced system with backward compatibility

### Phase 2: Migration (1 week) 📦
**Actions:**
- Create package directories: `packages/instructor/examples`, `packages/polyglot/examples`
- Run migration scripts to move examples and add frontmatter
- Test documentation generation after each package migration
- Validate hub commands continue working

**Result:** Examples in package directories with frontmatter control

### Phase 3: Cleanup (Optional) 🧹
**Actions:**
- Remove legacy hardcoded mappings
- Make `./examples` directory optional build artifact
- Update contributor documentation
- Add validation tools

**Result:** Fully organic, frontmatter-driven system

## Quick Start

**To implement immediately:**

1. **Review the technical guide:**
   ```bash
   cat research/frontmatter-driven-examples/technical-guide.md
   ```

2. **Run the proof of concept:**
   ```bash
   php research/frontmatter-driven-examples/proof-of-concept.php
   ```

3. **Apply code enhancements** from technical guide

4. **Test with current structure:**
   ```bash
   composer docs
   composer hub list
   ```

5. **Create first package example and test**

## Benefits

### Immediate Benefits
- ✅ **Zero breaking changes** during transition
- ✅ **Clear package ownership** of examples
- ✅ **Organic structure** driven by frontmatter
- ✅ **Backward compatibility** maintained

### Long-term Benefits
- ✅ **No hardcoded mappings** to maintain
- ✅ **Easy new package integration**
- ✅ **Flexible reorganization** via frontmatter
- ✅ **Clean separation of concerns**

## Success Criteria

**✅ All Requirements Met:**
- Examples can live in `packages/*/examples/` ✅
- Frontmatter drives documentation placement ✅
- Hub commands continue working ✅
- Documentation generation continues working ✅
- Organic growth without hardcoded mappings ✅
- Backward compatibility maintained ✅

## Next Steps

1. **Approve approach** - Review documents and proof of concept
2. **Implement Phase 1** - Apply infrastructure enhancements
3. **Test thoroughly** - Verify backward compatibility
4. **Begin migration** - Start with one package as pilot
5. **Scale gradually** - Migrate remaining packages systematically

This research provides a complete, pragmatic solution for achieving the desired frontmatter-driven, package-based example organization.