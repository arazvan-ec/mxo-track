# Agent: Codebase Analyzer

Research agent for understanding codebase structure and patterns.

## Purpose

Analyze existing codebase to understand patterns, structure, and conventions before implementing new features.

## When to Use

- New project onboarding
- Before planning a feature
- Understanding existing patterns
- Finding reference implementations
- Documenting architecture

## Responsibilities

- Map project structure
- Identify frameworks and libraries
- Document existing patterns
- Find reference implementations
- Assess code complexity
- Generate context files

## Analysis Process

### Step 1: Project Structure

```bash
# Identify project type
ls -la
cat package.json 2>/dev/null || cat composer.json 2>/dev/null

# Map directory structure
find . -type d -name "src" -o -name "app" -o -name "lib" | head -20

# Count files by type
find . -name "*.ts" -o -name "*.tsx" | wc -l
find . -name "*.php" | wc -l
```

### Step 2: Framework Detection

```bash
# Backend
grep -l "symfony" composer.json 2>/dev/null
grep -l "laravel" composer.json 2>/dev/null
grep -l "express" package.json 2>/dev/null

# Frontend
grep -l "react" package.json 2>/dev/null
grep -l "vue" package.json 2>/dev/null
grep -l "angular" package.json 2>/dev/null
```

### Step 3: Pattern Identification

Look for:
- DDD structure (Domain/Application/Infrastructure)
- MVC structure (Controllers/Models/Views)
- Component patterns (atomic design)
- State management (Redux, Context, Zustand)
- Testing patterns
- HTTP client usage (Guzzle, HttpClient, fetch, axios, http.Client)
- External API integration patterns (adapters, mappers, ACL)
- Serialization/Transformation patterns (serializer groups, transformers, DTOs)
- Multi-platform response handling (platform-specific DTOs, response strategies)
- Async/concurrent HTTP patterns (Promise pools, async streams, goroutine groups)
- Data aggregation patterns (assemblers, builders for multi-source entities)

### Step 4: Reference Finding

For new features, find similar existing code:

```bash
# Find similar entities
find . -path "*/Domain/Entity/*.php" -type f

# Find similar components
find . -path "*/components/*Form.tsx" -type f

# Find similar use cases
find . -path "*/Application/UseCase/*.php" -type f
```

### Step 4b: External API Integration Detection

```bash
# Find HTTP client vendor usage
grep -rl "HttpClient\|GuzzleHttp\|Guzzle\|Http::get\|Http::post\|axios\|fetch(" src/ app/ lib/ 2>/dev/null | head -20

# Find outgoing HTTP adapters/providers
find . -path "*/Infrastructure/External/*" -o -path "*/Infrastructure/Http/Client/*" -o -path "*/Infrastructure/Api/*" -type f | head -20
find . -path "*/Adapter/*Client*" -o -path "*/Adapter/*Provider*" -type f | head -20

# Find port/interface for external APIs
grep -rl "ProviderInterface\|ClientInterface\|GatewayInterface\|ExternalApi" src/Domain/ src/domain/ 2>/dev/null | head -20

# Find serializers/transformers
find . -name "*Transformer*" -o -name "*Serializer*" -o -name "*Normalizer*" -type f | head -20
grep -rl "@Groups\|@SerializedName\|SerializationContext\|NormalizerInterface" src/ 2>/dev/null | head -10

# Find async HTTP patterns
grep -rl "Promise\|async\|await\|Pool\|stream()\|concurrent\|WaitGroup\|errgroup\|asyncio.gather" src/ app/ 2>/dev/null | head -10

# Find data assemblers/aggregators
find . -name "*Assembler*" -o -name "*Aggregator*" -o -name "*Composer*" -o -name "*Builder*Service*" -type f | head -10
```

### Step 4c: Detect Vendor SDK Leakage

```bash
# Check if vendor SDK types appear in Domain or Application layers
# PHP
grep -r "use GuzzleHttp\|use Symfony\\Component\\HttpClient\|use Symfony\\Contracts\\HttpClient" src/Domain/ src/Application/ 2>/dev/null
# TypeScript
grep -r "import.*from.*axios\|import.*from.*node-fetch\|import.*from.*got" src/domain/ src/application/ 2>/dev/null
# Go
grep -r "\"net/http\"\|\"github.com.*client\"" domain/ internal/domain/ 2>/dev/null
# Python
grep -r "import requests\|from httpx\|from aiohttp" domain/ application/ 2>/dev/null

# Expected: No results (domain/application should not import HTTP client SDKs)
```

### Step 4d: Dimensional Evidence Collection

Measure dimensional properties to feed into the API Architecture Diagnostic (`openspec/specs/api-architecture-diagnostic.yaml`):

```bash
# Data Flow: Count ingress vs egress patterns
# Controllers/handlers → egress; HTTP clients → ingress; both → aggregation/bidirectional
grep -rl "Controller\|Handler\|Endpoint" src/ 2>/dev/null | wc -l  # egress signals
grep -rl "HttpClient\|GuzzleHttp\|axios\|fetch\|http.Get" src/ 2>/dev/null | wc -l  # ingress signals

# Data Source Topology: Count data sources by type
find . -name "*.sql" -o -name "*migration*" -o -name "*schema*" 2>/dev/null | wc -l  # DB signals
find . -path "*/Infrastructure/External/*" -o -path "*/Adapter/*Client*" 2>/dev/null | wc -l  # External signals

# Consumer Diversity: Find platform-specific code
find . -name "*Mobile*" -o -name "*Web*" -o -name "*Api*DTO*" -type f 2>/dev/null | head -10

# Concurrency Model: Detect async patterns
grep -rl "Promise\|async\|await\|Pool\|stream()\|WaitGroup\|errgroup\|asyncio.gather" src/ 2>/dev/null | head -10

# Response Customization: Find transformer/serializer variants
find . -name "*Transformer*" -o -name "*Serializer*" -o -name "*Normalizer*" -type f 2>/dev/null | head -10
```

Output dimensional evidence in Context Report under "### Dimensional Profile":

```markdown
### Dimensional Profile (for API Architecture Diagnostic)

| Dimension | Detected Value | Evidence |
|-----------|---------------|----------|
| Data Flow | [ingress/egress/aggregation/...] | [evidence] |
| Data Source Topology | [single_db/multi_external/...] | [evidence] |
| Consumer Diversity | [single_consumer/multi_platform/...] | [evidence] |
| Dependency Isolation | [fully_isolated/direct_coupling/...] | [evidence] |
| Concurrency Model | [synchronous/async_capable/...] | [evidence] |
| Response Customization | [uniform/per_consumer_shaped/...] | [evidence] |
```

This profile feeds into `/workflows:discover` Step 6c for formal diagnostic generation.

## Output: Context Report

```markdown
# Codebase Analysis: ${PROJECT_NAME}

## Project Overview
- **Type**: Full-stack web application
- **Backend**: Symfony 6.4 (PHP 8.2)
- **Frontend**: React 18 (TypeScript 5)
- **Database**: PostgreSQL 15

## Directory Structure
```
project/
├── backend/
│   ├── src/
│   │   ├── Domain/          # DDD Domain layer
│   │   ├── Application/     # Use Cases, DTOs
│   │   └── Infrastructure/  # Controllers, Repos
│   └── tests/
└── frontend/
    ├── src/
    │   ├── components/      # Reusable UI
    │   ├── features/        # Feature modules
    │   └── services/        # API clients
    └── tests/
```

## Detected Patterns

### Backend
- **Architecture**: Domain-Driven Design
- **Entity Pattern**: Factory methods, no setters
- **Repository Pattern**: Interface in Domain, impl in Infrastructure
- **Use Case Pattern**: Single responsibility, DTO in/out

### External API Consumption
- **HTTP Client**: [Vendor SDK / Raw / None]
- **Integration Pattern**: [ACL / Direct / Adapter-only]
- **Vendor SDK Isolation**: [Properly wrapped / Leaking into domain]
- **Async HTTP**: [Concurrent / Sequential / N/A]
- **External APIs Found**: [list with SDK and adapter paths]

### Serialization & Multi-Platform
- **Serialization Strategy**: [Framework serializer / Manual DTO / Transformer Strategy]
- **Multi-Platform Output**: [Strategy per consumer / Serialization groups / Single format]
- **Platform-specific files found**: [list]

### Frontend
- **Component Pattern**: Functional with hooks
- **State Management**: React Context + custom hooks
- **Form Pattern**: react-hook-form + yup validation
- **API Pattern**: Custom hooks with loading/error states

## Reference Files

### For New Entity
- Reference: `src/Domain/Entity/Order.php`
- Tests: `tests/Unit/Domain/Entity/OrderTest.php`

### For New Use Case
- Reference: `src/Application/UseCase/CreateOrderUseCase.php`
- Tests: `tests/Unit/Application/CreateOrderUseCaseTest.php`

### For New Component
- Reference: `src/components/OrderForm.tsx`
- Tests: `src/__tests__/OrderForm.test.tsx`

## Complexity Assessment
- **Total Files**: 150
- **Lines of Code**: ~25,000
- **Test Coverage**: 75%
- **Technical Debt**: Low

## Recommendations
1. Follow existing Entity pattern for new entities
2. Use OrderForm as template for new forms
3. Maintain DDD layer separation
```

## Integration

Use before planning:
```bash
# Analyze the codebase using the process described above, then plan with context
/workflows:plan user-management
```
