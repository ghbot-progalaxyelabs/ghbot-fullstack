# WebMeteor - GitHub Issues

This document contains GitHub issues for implementing authentication, content persistence, and core website builder functionality.

---

## 🔐 Authentication & User Management

### Issue #1: Create users database table and migration

**Labels**: `backend`, `database`, `priority-high`

**Description**:
Create a PostgreSQL migration for the `users` table to store user information from Google Sign-in.

**Acceptance Criteria**:
- [ ] Create migration file: `api/migrations/001_create_users_table.sql`
- [ ] Table schema includes:
  - `id` UUID PRIMARY KEY
  - `email` VARCHAR(255) UNIQUE NOT NULL
  - `google_id` VARCHAR(255) UNIQUE NOT NULL
  - `name` VARCHAR(255)
  - `avatar_url` TEXT
  - `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
  - `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
- [ ] Add indexes on `email` and `google_id`
- [ ] Document migration in README

**Technical Notes**:
```sql
CREATE TABLE users (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    email VARCHAR(255) UNIQUE NOT NULL,
    google_id VARCHAR(255) UNIQUE NOT NULL,
    name VARCHAR(255),
    avatar_url TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX idx_users_email ON users(email);
CREATE INDEX idx_users_google_id ON users(google_id);
```

---

### Issue #2: Implement Google Sign-in backend route

**Labels**: `backend`, `authentication`, `priority-high`

**Description**:
Create a backend route to verify Google JWT tokens and create/retrieve users.

**Acceptance Criteria**:
- [ ] Create `POST /auth/google-signin` route
- [ ] Verify Google JWT token using Google API
- [ ] Extract user info: email, google_id, name, avatar_url
- [ ] Create user if doesn't exist (INSERT)
- [ ] Retrieve existing user if exists (SELECT)
- [ ] Generate app JWT token with user_id in claims
- [ ] Return JWT token and user info in response
- [ ] Handle errors: invalid token, database errors

**API Contract**:
```typescript
Request:
POST /auth/google-signin
{
  googleToken: string  // JWT from Google
}

Response:
{
  token: string,      // App JWT
  user: {
    id: string,
    email: string,
    name: string,
    avatarUrl: string
  }
}
```

**Dependencies**: Issue #1

---

### Issue #3: Create JWT authentication middleware

**Labels**: `backend`, `authentication`, `priority-high`

**Description**:
Implement middleware to validate JWT tokens and extract user information for protected routes.

**Acceptance Criteria**:
- [ ] Create `Framework/Middleware/AuthMiddleware.php`
- [ ] Validate JWT signature and expiration
- [ ] Extract `user_id` from token claims
- [ ] Inject `userId` into route handlers
- [ ] Return 401 for missing/invalid tokens
- [ ] Handle token expiration gracefully
- [ ] Add middleware to Router pipeline

**Technical Notes**:
- Use existing `firebase/php-jwt` library
- Check `Authorization: Bearer <token>` header
- Set token expiration to 7 days

**Dependencies**: Issue #2

---

### Issue #4: Update frontend AuthService to store JWT token

**Labels**: `frontend`, `authentication`, `priority-high`

**Description**:
Enhance the Angular AuthService to store and manage JWT tokens from the backend.

**Acceptance Criteria**:
- [ ] Store JWT in localStorage after Google Sign-in
- [ ] Add `token: BehaviorSubject<string | null>` to AuthService
- [ ] Add `user: BehaviorSubject<User | null>` for user info
- [ ] Implement `signIn(googleToken: string)` method
- [ ] Call backend `/auth/google-signin` route
- [ ] Store returned JWT and user info
- [ ] Implement `signOut()` to clear token and user
- [ ] Implement `getToken()` helper method
- [ ] Add token refresh logic (if expired)

**Technical Notes**:
```typescript
interface User {
  id: string;
  email: string;
  name: string;
  avatarUrl: string;
}
```

**Dependencies**: Issue #2

---

### Issue #5: Update API client to include Authorization header

**Labels**: `frontend`, `authentication`, `priority-high`

**Description**:
Modify the auto-generated API client to automatically include JWT tokens in requests.

**Acceptance Criteria**:
- [ ] Update `www/api-client/src/index.ts` generator template
- [ ] Inject AuthService into API client
- [ ] Add `Authorization: Bearer <token>` header to all requests
- [ ] Handle 401 responses (redirect to sign-in)
- [ ] Update client generation script to include auth logic
- [ ] Regenerate TypeScript client

**Technical Notes**:
- May need to modify `api/Framework/cli/generate-client.php`
- Consider making AuthService a parameter to api functions

**Dependencies**: Issue #4

---

### Issue #6: Protect website creation route with authentication

**Labels**: `backend`, `authentication`, `priority-high`

**Description**:
Update `POST /websites` route to require authentication and associate websites with users.

**Acceptance Criteria**:
- [ ] Add auth middleware to `/websites` route
- [ ] Update `WebsitesRoute` to use injected `userId`
- [ ] Make `user_id` NOT NULL in database INSERT
- [ ] Return 401 if not authenticated
- [ ] Update API contract documentation
- [ ] Test with valid and invalid tokens

**Technical Notes**:
- `user_id` should come from JWT middleware, not request body
- Remove `userId` from `WebsitesRequest` DTO

**Dependencies**: Issue #3

---

## 💾 Content Persistence

### Issue #7: Add content column to websites table

**Labels**: `backend`, `database`, `priority-high`

**Description**:
Add a JSONB column to store website content (pages, sections, layouts, etc.).

**Acceptance Criteria**:
- [ ] Create migration: `002_add_content_to_websites.sql`
- [ ] Add `content JSONB` column to `websites` table
- [ ] Add `settings JSONB` column for logos, colors, navbar
- [ ] Set default values to empty JSON objects
- [ ] Create GIN index on content for faster queries
- [ ] Document JSON schema structure

**Migration**:
```sql
ALTER TABLE websites
  ADD COLUMN content JSONB DEFAULT '{}',
  ADD COLUMN settings JSONB DEFAULT '{}';

CREATE INDEX idx_websites_content ON websites USING GIN (content);
```

**Dependencies**: None

---

### Issue #8: Create GET /websites/:id route to fetch website with content

**Labels**: `backend`, `api`, `priority-high`

**Description**:
Implement route to retrieve a website by ID with all content for the editor.

**Acceptance Criteria**:
- [ ] Create `GET /websites/:id` route using framework generator
- [ ] Validate user owns the website (user_id matches JWT)
- [ ] Return 403 if user doesn't own website
- [ ] Return 404 if website not found
- [ ] Include all fields: id, name, type, content, settings, status
- [ ] Create DTOs: `GetWebsiteRequest`, `GetWebsiteResponse`
- [ ] Generate TypeScript client
- [ ] Add unit tests

**API Contract**:
```typescript
Response:
{
  id: string,
  name: string,
  type: 'portfolio' | 'business' | 'ecommerce' | 'blog',
  content: {
    pages: Array<Page>,
    // ... full website structure
  },
  settings: {
    logo: string,
    colors: object,
    navbar: object
  },
  status: string,
  createdAt: string,
  updatedAt: string
}
```

**Dependencies**: Issue #6, Issue #7

---

### Issue #9: Create PUT /websites/:id route to save website content

**Labels**: `backend`, `api`, `priority-high`

**Description**:
Implement route to update website content from the editor.

**Acceptance Criteria**:
- [ ] Create `PUT /websites/:id` route
- [ ] Validate user owns the website (authorization check)
- [ ] Accept `content` and `settings` in request body
- [ ] Update `content`, `settings`, and `updated_at` in database
- [ ] Return updated website data
- [ ] Validate JSON structure
- [ ] Create DTOs: `UpdateWebsiteRequest`, `UpdateWebsiteResponse`
- [ ] Generate TypeScript client
- [ ] Add unit tests

**API Contract**:
```typescript
Request:
PUT /websites/:id
{
  name?: string,
  content?: object,
  settings?: object
}

Response:
{
  id: string,
  updatedAt: string
}
```

**Dependencies**: Issue #6, Issue #7

---

### Issue #10: Implement website serialization in frontend

**Labels**: `frontend`, `editor`, `priority-high`

**Description**:
Create methods to serialize the Website class to JSON for saving to the backend.

**Acceptance Criteria**:
- [ ] Add `toJSON()` method to `Website` class
- [ ] Add `toJSON()` method to `WebsitePage` class
- [ ] Add `toJSON()` method to `WebsiteSection` class
- [ ] Serialize all properties: pages, sections, properties, colors
- [ ] Exclude DOM references (iframe, elements)
- [ ] Handle circular references
- [ ] Add unit tests for serialization

**Technical Notes**:
- Located in `www/src/app/pages/editor/website.service.ts`
- Need to convert HTMLElement references to string IDs or HTML strings

**Dependencies**: None

---

### Issue #11: Implement website deserialization in frontend

**Labels**: `frontend`, `editor`, `priority-high`

**Description**:
Create methods to restore Website object from JSON loaded from the backend.

**Acceptance Criteria**:
- [ ] Add `fromJSON()` static method to `Website` class
- [ ] Reconstruct pages array from JSON
- [ ] Reconstruct sections with DOM elements in iframe
- [ ] Restore properties, colors, settings
- [ ] Re-attach event listeners
- [ ] Set active page correctly
- [ ] Handle missing or malformed data gracefully
- [ ] Add unit tests for deserialization

**Technical Notes**:
- Must rebuild iframe DOM structure
- Recreate WebsiteSection instances with proper element references

**Dependencies**: Issue #10

---

### Issue #12: Add Save button and functionality to editor

**Labels**: `frontend`, `editor`, `priority-high`

**Description**:
Add a Save button to the editor toolbar that saves website content to the backend.

**Acceptance Criteria**:
- [ ] Add "Save" button to editor toolbar UI
- [ ] Add keyboard shortcut: Ctrl+S / Cmd+S
- [ ] Implement `saveWebsite()` method in WebsiteService
- [ ] Serialize website using `toJSON()`
- [ ] Call `api.putWebsite(id, content)`
- [ ] Show save status: "Saving...", "Saved", "Error"
- [ ] Display timestamp of last save
- [ ] Disable button during save
- [ ] Show error message if save fails

**UI Design**:
```
[Save] [Last saved: 2 minutes ago]
```

**Dependencies**: Issue #9, Issue #10

---

### Issue #13: Load website content when editor opens

**Labels**: `frontend`, `editor`, `priority-high`

**Description**:
Load website content from the backend when the editor initializes.

**Acceptance Criteria**:
- [ ] Get website ID from route params (`/editor/:id`)
- [ ] Call `api.getWebsite(id)` on component init
- [ ] Show loading spinner while fetching
- [ ] Deserialize JSON to Website object using `fromJSON()`
- [ ] Handle 404: show "Website not found" message
- [ ] Handle 403: redirect to websites list
- [ ] Handle network errors gracefully
- [ ] Initialize iframe with loaded content

**Technical Notes**:
- Modify `www/src/app/pages/editor/editor.component.ts`
- Currently creates empty website, should load from DB

**Dependencies**: Issue #8, Issue #11

---

### Issue #14: Track unsaved changes in editor

**Labels**: `frontend`, `editor`, `priority-medium`

**Description**:
Add dirty state tracking to warn users about unsaved changes.

**Acceptance Criteria**:
- [ ] Add `hasUnsavedChanges: boolean` flag to WebsiteService
- [ ] Set flag to true on any editor action
- [ ] Reset flag to false after successful save
- [ ] Show visual indicator when there are unsaved changes
- [ ] Add `beforeunload` event listener to warn on page close
- [ ] Show confirmation dialog when navigating away with unsaved changes
- [ ] Implement CanDeactivate route guard

**UI Design**:
```
[Save*] [Last saved: 2 minutes ago]  <- asterisk indicates unsaved
```

**Dependencies**: Issue #12

---

## 📋 Website Management

### Issue #15: Create GET /websites route to list user's websites

**Labels**: `backend`, `api`, `priority-medium`

**Description**:
Implement route to retrieve all websites belonging to the authenticated user.

**Acceptance Criteria**:
- [ ] Create `GET /websites` route
- [ ] Require authentication (JWT middleware)
- [ ] Query websites where `user_id` matches authenticated user
- [ ] Return array of websites with basic info (no full content)
- [ ] Include: id, name, type, status, created_at, updated_at
- [ ] Order by `updated_at DESC`
- [ ] Add pagination support (optional)
- [ ] Create DTOs and generate TypeScript client

**API Contract**:
```typescript
Response:
{
  websites: Array<{
    id: string,
    name: string,
    type: string,
    status: string,
    createdAt: string,
    updatedAt: string
  }>
}
```

**Dependencies**: Issue #6

---

### Issue #16: Create "My Websites" list page in frontend

**Labels**: `frontend`, `ui`, `priority-medium`

**Description**:
Create a page to display all websites owned by the user.

**Acceptance Criteria**:
- [ ] Create `MyWebsitesComponent` at `/my-websites` route
- [ ] Call `api.getWebsites()` to fetch user's websites
- [ ] Display websites in grid/card layout
- [ ] Show: name, type, thumbnail (placeholder), last updated
- [ ] Add "Edit" button → navigate to `/editor/:id`
- [ ] Add "Delete" button with confirmation modal
- [ ] Show empty state: "No websites yet. Create one!"
- [ ] Add loading spinner
- [ ] Handle errors gracefully

**UI Mockup**:
```
My Websites
[+ New Website]

┌─────────────┐ ┌─────────────┐ ┌─────────────┐
│ My Portfolio│ │ Business Co │ │ Blog Site   │
│ Portfolio   │ │ Business    │ │ Blog        │
│ Updated 2h  │ │ Updated 1d  │ │ Updated 3d  │
│ [Edit] [Del]│ │ [Edit] [Del]│ │ [Edit] [Del]│
└─────────────┘ └─────────────┘ └─────────────┘
```

**Dependencies**: Issue #15

---

### Issue #17: Create DELETE /websites/:id route

**Labels**: `backend`, `api`, `priority-medium`

**Description**:
Implement route to delete a website.

**Acceptance Criteria**:
- [ ] Create `DELETE /websites/:id` route
- [ ] Require authentication
- [ ] Validate user owns the website
- [ ] Soft delete: set `status = 'deleted'` (preferred)
- [ ] OR hard delete: `DELETE FROM websites WHERE id = :id`
- [ ] Return 204 No Content on success
- [ ] Return 403 if user doesn't own website
- [ ] Return 404 if website not found
- [ ] Generate TypeScript client

**Technical Notes**:
- Recommend soft delete to allow recovery
- Could add `deleted_at` timestamp column

**Dependencies**: Issue #6

---

### Issue #18: Add delete functionality to My Websites page

**Labels**: `frontend`, `ui`, `priority-medium`

**Description**:
Implement delete functionality with confirmation on the My Websites page.

**Acceptance Criteria**:
- [ ] Add delete button to each website card
- [ ] Show confirmation modal: "Are you sure you want to delete [name]?"
- [ ] Call `api.deleteWebsite(id)` on confirmation
- [ ] Remove website from list on success
- [ ] Show success toast: "Website deleted"
- [ ] Handle errors: show error message
- [ ] Disable delete button during deletion

**Dependencies**: Issue #16, Issue #17

---

## 🚀 User Experience Improvements

### Issue #19: Require authentication before website creation

**Labels**: `frontend`, `authentication`, `priority-high`

**Description**:
Add route guard to require users to sign in before creating websites.

**Acceptance Criteria**:
- [ ] Create `AuthGuard` service
- [ ] Check if user is authenticated (has valid token)
- [ ] Apply guard to `/website-wizard` route
- [ ] Redirect to home page if not authenticated
- [ ] Show sign-in prompt/modal
- [ ] After sign-in, redirect to originally requested route
- [ ] Store redirect URL in AuthService

**Technical Notes**:
```typescript
canActivate(): boolean {
  if (!this.authService.isAuthenticated()) {
    this.router.navigate(['/']);
    // Show sign-in modal
    return false;
  }
  return true;
}
```

**Dependencies**: Issue #4

---

### Issue #20: Update navbar to show user info when signed in

**Labels**: `frontend`, `ui`, `priority-medium`

**Description**:
Display user information in the navbar after successful sign-in.

**Acceptance Criteria**:
- [ ] Replace "Sign in" dropdown with user avatar/name when authenticated
- [ ] Show user name and email in dropdown
- [ ] Add "My Websites" link to dropdown
- [ ] Add "Sign out" button to dropdown
- [ ] Clear token and redirect to home on sign out
- [ ] Show avatar image if available
- [ ] Update UI reactively when auth status changes

**UI Mockup**:
```
Before: [Sign in ▼]
After:  [John Doe ▼]
         My Websites
         john@example.com
         ───────────
         Sign out
```

**Dependencies**: Issue #4

---

### Issue #21: Implement auto-save functionality

**Labels**: `frontend`, `editor`, `priority-low`

**Description**:
Automatically save website content every 30 seconds if changes are detected.

**Acceptance Criteria**:
- [ ] Create auto-save interval (30 seconds)
- [ ] Only save if `hasUnsavedChanges` is true
- [ ] Show "Auto-saving..." indicator
- [ ] Clear interval when component is destroyed
- [ ] Disable auto-save if manual save is in progress
- [ ] Add setting to enable/disable auto-save
- [ ] Update "Last saved" timestamp on success
- [ ] Handle errors silently (don't block user)

**Technical Notes**:
- Use RxJS interval or setTimeout
- Consider debouncing rapid changes

**Dependencies**: Issue #12, Issue #14

---

### Issue #22: Add loading state to website wizard

**Labels**: `frontend`, `ui`, `priority-low`

**Description**:
Improve UX by showing loading state during website creation.

**Acceptance Criteria**:
- [ ] Disable form during submission
- [ ] Show loading spinner on "Next" button
- [ ] Change button text to "Creating..."
- [ ] Prevent double-submission
- [ ] Show progress indicator
- [ ] Handle slow network gracefully

**Current State**: Basic `isSubmitting` flag exists but UI could be improved

**Dependencies**: None

---

### Issue #23: Add website thumbnails/screenshots

**Labels**: `frontend`, `backend`, `priority-low`

**Description**:
Generate and display thumbnail images for websites in the list view.

**Acceptance Criteria**:
- [ ] Add `thumbnail_url` column to websites table
- [ ] Generate screenshot of website on save (using headless browser or canvas)
- [ ] Store thumbnail in cloud storage or as base64
- [ ] Display thumbnail in My Websites grid
- [ ] Fallback to placeholder image if no thumbnail
- [ ] Regenerate thumbnail on significant changes

**Technical Notes**:
- Could use html2canvas or Puppeteer
- Consider image optimization (WebP, resize)
- Might need separate background job

**Dependencies**: Issue #16

---

## 🔧 Technical Debt & Refactoring

### Issue #24: Remove hardcoded Google Client ID

**Labels**: `frontend`, `security`, `priority-medium`

**Description**:
Move hardcoded Google OAuth client ID to environment configuration.

**Acceptance Criteria**:
- [ ] Add `GOOGLE_CLIENT_ID` to environment variables
- [ ] Update `app.component.ts` to use env variable
- [ ] Document in `.env.example`
- [ ] Update Docker configuration to pass env var
- [ ] Remove hardcoded value: `108864518050-fjhjlifc56klj8rsmm4r9tmn9p7j632d.apps.googleusercontent.com`

**Current Location**: `www/src/app/app.component.ts:42`

**Dependencies**: None

---

### Issue #25: Remove dead code from website wizard

**Labels**: `frontend`, `refactor`, `priority-low`

**Description**:
Clean up commented-out code and unused navigation logic in website wizard.

**Acceptance Criteria**:
- [ ] Remove commented switch statement (lines 65-81)
- [ ] Remove unused route components if they exist:
  - `/website-wizard/portfolio-content`
  - `/website-wizard/business-content`
  - `/website-wizard/ecommerce-content`
  - `/website-wizard/blog-content`
- [ ] Update documentation

**Current Location**: `www/src/app/pages/website-wizard/website-wizard.component.ts:65-81`

**Dependencies**: None

---

### Issue #26: Fix non-existent signin endpoint reference

**Labels**: `frontend`, `bug`, `priority-medium`

**Description**:
The frontend tries to verify Google token with a non-existent backend endpoint.

**Acceptance Criteria**:
- [ ] Remove or update `verifyToken()` call in `app.component.ts:82`
- [ ] Use the new `/auth/google-signin` endpoint instead
- [ ] Remove hardcoded URL: `http://localhost:9100/access/signin`
- [ ] Integrate with AuthService properly

**Current Location**: `www/src/app/app.component.ts:82-96`

**Dependencies**: Issue #2, Issue #4

---

### Issue #27: Add database migration runner to docker-compose

**Labels**: `backend`, `devops`, `priority-medium`

**Description**:
Automate database migrations on container startup.

**Acceptance Criteria**:
- [ ] Create migrations directory structure
- [ ] Add migration runner script
- [ ] Run migrations automatically when db container starts
- [ ] Add CLI command: `php migrate up/down`
- [ ] Track migration state in database
- [ ] Document migration workflow

**Technical Notes**:
- Framework already has `Framework/Migrations.php`
- Could use init script in Docker

**Dependencies**: None

---

### Issue #28: Add API error handling and validation messages

**Labels**: `backend`, `api`, `priority-medium`

**Description**:
Improve error responses with detailed validation messages.

**Acceptance Criteria**:
- [ ] Return field-specific validation errors
- [ ] Use standard error format across all routes
- [ ] Add error codes for different error types
- [ ] Include helpful error messages
- [ ] Document error responses in API docs

**Example Response**:
```json
{
  "status": "error",
  "code": "VALIDATION_ERROR",
  "message": "Invalid input",
  "errors": {
    "name": ["Name must be at least 3 characters"],
    "type": ["Type must be one of: portfolio, business, ecommerce, blog"]
  }
}
```

**Dependencies**: None

---

### Issue #29: Add unit tests for WebsitesRoute

**Labels**: `backend`, `testing`, `priority-low`

**Description**:
Create PHPUnit tests for website creation and management routes.

**Acceptance Criteria**:
- [ ] Test successful website creation
- [ ] Test validation errors (short name, invalid type)
- [ ] Test authentication requirement
- [ ] Test user ownership validation
- [ ] Test database insertion
- [ ] Test error handling
- [ ] Achieve >80% code coverage

**Dependencies**: Issue #6

---

### Issue #30: Add E2E tests for website creation flow

**Labels**: `frontend`, `testing`, `priority-low`

**Description**:
Create end-to-end tests for the complete website creation workflow.

**Acceptance Criteria**:
- [ ] Test: User signs in with Google
- [ ] Test: User navigates to website wizard
- [ ] Test: User fills form and creates website
- [ ] Test: User is redirected to editor
- [ ] Test: User saves website content
- [ ] Test: User navigates to My Websites
- [ ] Test: User can edit and delete websites
- [ ] Use Cypress or Playwright

**Dependencies**: Multiple (full workflow implementation)

---

## 📚 Documentation

### Issue #31: Document authentication flow

**Labels**: `documentation`, `priority-low`

**Description**:
Create comprehensive documentation for the authentication system.

**Acceptance Criteria**:
- [ ] Create `docs/AUTHENTICATION.md`
- [ ] Diagram: Google Sign-in flow
- [ ] Diagram: JWT validation flow
- [ ] API endpoints documentation
- [ ] Frontend integration guide
- [ ] Security considerations
- [ ] Token refresh strategy

**Dependencies**: Issues #2, #3, #4

---

### Issue #32: Document content persistence architecture

**Labels**: `documentation`, `priority-low`

**Description**:
Document how website content is saved and loaded.

**Acceptance Criteria**:
- [ ] Create `docs/CONTENT_PERSISTENCE.md`
- [ ] Document JSON schema for website content
- [ ] Explain serialization/deserialization
- [ ] API endpoints documentation
- [ ] Database schema diagrams
- [ ] Example payloads

**Dependencies**: Issues #7, #8, #9, #10, #11

---

### Issue #33: Update README with setup instructions

**Labels**: `documentation`, `priority-medium`

**Description**:
Update the main README with complete setup and development instructions.

**Acceptance Criteria**:
- [ ] Prerequisites section (Docker, Node, PHP)
- [ ] Environment setup (.env configuration)
- [ ] Google OAuth setup instructions
- [ ] Docker commands (build, run, stop)
- [ ] Database migration instructions
- [ ] Development workflow
- [ ] Troubleshooting section
- [ ] API documentation link

**Dependencies**: None

---

## 🎯 Implementation Priority

### Phase 1 - Authentication (Must Have)
- Issue #1: Create users table
- Issue #2: Google Sign-in backend route
- Issue #3: JWT middleware
- Issue #4: Frontend AuthService
- Issue #5: API client auth headers
- Issue #6: Protect website routes
- Issue #19: Require auth for creation
- Issue #26: Fix signin endpoint

### Phase 2 - Content Persistence (Must Have)
- Issue #7: Add content column
- Issue #8: GET /websites/:id
- Issue #9: PUT /websites/:id
- Issue #10: Website serialization
- Issue #11: Website deserialization
- Issue #12: Save button
- Issue #13: Load website content

### Phase 3 - Website Management (Should Have)
- Issue #15: GET /websites list
- Issue #16: My Websites page
- Issue #17: DELETE /websites/:id
- Issue #18: Delete functionality
- Issue #20: User info in navbar

### Phase 4 - UX Improvements (Nice to Have)
- Issue #14: Track unsaved changes
- Issue #21: Auto-save
- Issue #22: Loading states
- Issue #23: Thumbnails

### Phase 5 - Polish (Low Priority)
- Issue #24-30: Technical debt & testing
- Issue #31-33: Documentation

---

**Total Issues**: 33

**Estimated Timeline**:
- Phase 1: 1-2 weeks
- Phase 2: 1-2 weeks
- Phase 3: 1 week
- Phase 4: 1 week
- Phase 5: Ongoing

---

## Notes for Implementation

1. **Start with Phase 1** - Authentication is foundational for all other features
2. **Test incrementally** - Verify each issue works before moving to the next
3. **Update docs** - Keep documentation in sync as you implement
4. **Use feature branches** - Create branches like `feature/issue-1-users-table`
5. **Review security** - Pay special attention to auth and authorization
6. **Consider backwards compatibility** - Handle existing websites with no user_id

---

*Generated: 2025-11-09*
*Based on: WebMeteor website builder codebase analysis*
