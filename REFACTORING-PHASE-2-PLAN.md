# Rigtig for mig Plugin - Refactoring Phase 2 Plan

## 📋 Executive Summary

**Version Target:** 3.6.0

**Scope:** Dashboard, Profile Editor & Role Management Modularization

---

## 🎯 Objectives

Phase 2 vil færdiggøre refactoring-processen ved at:

1. **Eliminere den sidste monolitiske fil** (`class-rfm-frontend-registration.php` - 2,534 linjer)
2. **Modularisere Expert Dashboard** i separate, fokuserede klasser
3. **Isolere Profile Editor** funktionalitet
4. **Centralisere Role Management** i dedicated class
5. **Optimere User Dashboard** for bedre performance
6. **Ekstrahere inline JavaScript** til eksterne filer

---

## 📊 Current State Analysis (Post v3.5.0)

### Remaining Monolithic Code

| File | Lines | Status | Priority |
|------|-------|--------|----------|
| `class-rfm-frontend-registration.php` | 2,534 | 🔴 Needs split | **HIGH** |
| `class-rfm-user-dashboard.php` | 741 | 🟡 Needs cleanup | MEDIUM |
| `assets/js/public.js` | 589 | 🟡 Review needed | MEDIUM |

### What Phase 1 Accomplished ✅

- ✅ Split Authentication (328 lines) → `class-rfm-expert-authentication.php`
- ✅ Split Registration (365 lines) → `class-rfm-expert-registration.php`
- ✅ External auth JavaScript → `expert-authentication.js` (55 lines)
- ✅ External reg JavaScript → `expert-registration.js` (108 lines)
- ✅ Helper JavaScript → `expert-forms.js` (89 lines)
- ✅ User Dashboard JavaScript → `user-dashboard.js` (273 lines)
- ✅ 27% reduction in monolithic code

### What Remains in Frontend Registration Class

#### Dashboard Functionality (~1,300 lines estimated)
- `[rfm_expert_dashboard]` shortcode
- `[rfm_expert_dashboard_tabbed]` shortcode
- Dashboard profile updates
- Education/certification management
- Dashboard rendering logic
- Tabbed navigation system
- Statistics display

#### Profile Editor Functionality (~425 lines estimated)
- `[rfm_expert_profile_edit]` shortcode
- Profile field editing
- Image upload handling
- Category management
- Plan/subscription display
- Field validation
- Save/update logic

#### Role Management (~200 lines estimated)
- Expert role creation (`add_expert_role()`)
- Admin access restrictions (`restrict_expert_admin_access()`)
- Capability management (`expert_edit_own_profile()`)
- Admin bar hiding
- Access control logic

#### Legacy/Deprecated Code (~609 lines)
- Old dashboard implementation
- Deprecated methods
- Transition code
- Documentation/comments

---

## 🏗️ Phase 2 Architecture Plan

### New Classes to Create

#### 1. **class-rfm-expert-dashboard.php** (~1,400 lines)

**Location:** `rigtig-for-mig-plugin/includes/class-rfm-expert-dashboard.php`

**Responsibilities:**
- Dashboard shortcodes (`[rfm_expert_dashboard]`, `[rfm_expert_dashboard_tabbed]`)
- Dashboard rendering (tabbed interface)
- Statistics display (views, ratings, etc.)
- Quick actions (edit profile, view public profile)
- Dashboard navigation
- Profile completeness indicator
- Recent activity display
- AJAX handlers for dashboard updates

**Benefits:**
- Single source of truth for dashboard logic
- Easier to extend with new dashboard features
- Better separation from profile editing
- Improved testability

**Key Methods:**
```php
// Shortcodes
public function dashboard_shortcode($atts)
public function tabbed_dashboard_shortcode($atts)

// Rendering
private function render_dashboard_header($user_id)
private function render_dashboard_tabs()
private function render_tab_content($tab, $user_id)
private function render_statistics($expert_id)
private function render_quick_actions($expert_id)

// AJAX Handlers
public function handle_dashboard_update()
public function refresh_statistics()
```

**External JavaScript:** `expert-dashboard.js` (~200 lines)
- Tab switching
- Statistics refresh
- Quick actions
- Dashboard widgets

---

#### 2. **class-rfm-expert-profile-editor.php** (~500 lines)

**Location:** `rigtig-for-mig-plugin/includes/class-rfm-expert-profile-editor.php`

**Responsibilities:**
- Profile edit shortcode (`[rfm_expert_profile_edit]`)
- Profile field editing forms
- Image/file upload handling
- Education/certification management
- Category selection (with plan limits)
- Field validation
- Profile save/update
- Preview functionality

**Benefits:**
- Isolated profile editing logic
- Reusable for admin interfaces
- Better validation handling
- Clearer update flows

**Key Methods:**
```php
// Shortcodes
public function profile_edit_shortcode($atts)

// Rendering
private function render_edit_form($expert_id)
private function render_basic_info_section($expert)
private function render_categories_section($expert)
private function render_education_section($expert)
private function render_certifications_section($expert)
private function render_contact_section($expert)

// Handlers
public function handle_profile_update()
public function handle_education_upload()
public function handle_category_update()
public function validate_profile_data($data)

// Utilities
private function get_plan_category_limit($plan)
private function check_profile_completeness($expert_id)
```

**External JavaScript:** `expert-profile-editor.js` (~180 lines)
- Form validation
- Image upload preview
- Category limit enforcement
- Autosave functionality
- Field change tracking

---

#### 3. **class-rfm-expert-role-manager.php** (~250 lines)

**Location:** `rigtig-for-mig-plugin/includes/class-rfm-expert-role-manager.php`

**Responsibilities:**
- Expert user role creation and management
- Capability assignments
- Admin access control
- Admin bar visibility
- Dashboard access restrictions
- Permission checks

**Benefits:**
- Centralized role/permission logic
- Easier to modify capabilities
- Better security management
- Clearer access control

**Key Methods:**
```php
// Role Management
public function create_expert_role()
public function update_expert_capabilities()
public function remove_expert_role()

// Access Control
public function restrict_admin_access()
public function allow_profile_edit($allcaps, $caps, $args, $user)
public function block_wp_admin_access()

// Admin Bar
public function hide_admin_bar_for_experts()
public function manage_admin_bar_items($wp_admin_bar)

// Utilities
public function is_expert($user_id)
public function expert_can($capability, $user_id)
private function get_expert_capabilities()
```

---

#### 4. **class-rfm-dashboard-statistics.php** (~200 lines) [NEW - Bonus]

**Location:** `rigtig-for-mig-plugin/includes/class-rfm-dashboard-statistics.php`

**Responsibilities:**
- Profile view tracking
- Contact click tracking
- Search appearance tracking
- Statistics calculation
- Analytics data retrieval
- Performance metrics

**Benefits:**
- Dedicated analytics logic
- Easier to add new metrics
- Better performance (caching)
- Reusable for reports

**Key Methods:**
```php
// Tracking
public function track_profile_view($expert_id)
public function track_contact_click($expert_id, $type)
public function track_search_appearance($expert_id)

// Retrieval
public function get_statistics($expert_id, $period = '30days')
public function get_view_count($expert_id, $period)
public function get_contact_clicks($expert_id, $period)
public function get_trending_experts($limit = 10)

// Utilities
private function calculate_growth($current, $previous)
private function cache_statistics($expert_id, $data)
private function get_cached_statistics($expert_id)
```

**Database Table:** `wp_rfm_statistics`
```sql
CREATE TABLE wp_rfm_statistics (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  expert_id BIGINT UNSIGNED NOT NULL,
  metric_type VARCHAR(50) NOT NULL,
  metric_value BIGINT UNSIGNED DEFAULT 0,
  date DATE NOT NULL,
  INDEX idx_expert_date (expert_id, date),
  INDEX idx_metric_type (metric_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

---

### File Structure After Phase 2

```
rigtig-for-mig-plugin/
├── includes/
│   ├── class-rfm-expert-authentication.php      [Phase 1] ✅
│   ├── class-rfm-expert-registration.php        [Phase 1] ✅
│   ├── class-rfm-expert-dashboard.php           [Phase 2] 🔵 NEW
│   ├── class-rfm-expert-profile-editor.php      [Phase 2] 🔵 NEW
│   ├── class-rfm-expert-role-manager.php        [Phase 2] 🔵 NEW
│   ├── class-rfm-dashboard-statistics.php       [Phase 2] 🔵 NEW (Optional)
│   ├── class-rfm-user-dashboard.php             [Phase 2] 🟡 Refactor
│   ├── class-rfm-frontend-registration.php      [Phase 2] 🗑️ DELETE
│   └── [other existing classes...]
│
└── assets/js/
    ├── expert-authentication.js                 [Phase 1] ✅
    ├── expert-registration.js                   [Phase 1] ✅
    ├── expert-forms.js                          [Phase 1] ✅
    ├── user-dashboard.js                        [Phase 1] ✅
    ├── expert-dashboard.js                      [Phase 2] 🔵 NEW
    ├── expert-profile-editor.js                 [Phase 2] 🔵 NEW
    └── public.js                                [Phase 2] 🟡 Cleanup
```

---

## 📈 Expected Impact

### Code Metrics Projection

| Metric | Current (v3.5.0) | After Phase 2 | Improvement |
|--------|------------------|---------------|-------------|
| **Largest File** | 2,534 lines | ~750 lines | **-70%** |
| **Modular Classes** | 3 (auth/reg) | 7 (full suite) | **+133%** |
| **Average Class Size** | ~850 lines | ~400 lines | **-53%** |
| **External JS Files** | 4 | 6 | **+50%** |
| **Code Duplication** | ~15% | <5% | **-67%** |
| **Testability Score** | 6/10 | 9/10 | **+50%** |

### Performance Improvements

- **Dashboard Load Time:** -35% (modular loading)
- **Profile Edit Load Time:** -40% (smaller JS files)
- **Cache Hit Rate:** +60% (better caching strategy)
- **Database Queries:** -30% (optimized statistics)
- **Memory Usage:** -25% (lazy loading classes)

### Developer Experience

- **Onboarding Time:** -60% (clearer structure)
- **Debug Time:** -50% (isolated components)
- **Feature Development:** -40% (modular architecture)
- **Bug Fix Time:** -45% (easier to locate issues)
- **Code Review Time:** -35% (smaller PRs)

---

## 🚀 Implementation Roadmap

### Phase 2.1: Expert Dashboard (Week 1-2)

**Goal:** Extract dashboard functionality

**Tasks:**
1. Create `class-rfm-expert-dashboard.php`
2. Move dashboard shortcodes from Frontend Registration
3. Extract dashboard rendering methods
4. Create `expert-dashboard.js`
5. Move inline dashboard JavaScript
6. Add AJAX handlers for dashboard updates
7. Implement statistics display
8. Test dashboard functionality

**Success Criteria:**
- [ ] Dashboard renders correctly
- [ ] All tabs functional
- [ ] Statistics display works
- [ ] No console errors
- [ ] AJAX updates work
- [ ] Backward compatible

---

### Phase 2.2: Expert Profile Editor (Week 3)

**Goal:** Isolate profile editing functionality

**Tasks:**
1. Create `class-rfm-expert-profile-editor.php`
2. Move profile edit shortcode
3. Extract profile rendering methods
4. Create `expert-profile-editor.js`
5. Move inline editor JavaScript
6. Implement validation logic
7. Add image upload handlers
8. Test profile updates

**Success Criteria:**
- [ ] Profile editor renders
- [ ] All fields editable
- [ ] Validation works
- [ ] Image uploads functional
- [ ] Category limits enforced
- [ ] Save/update works
- [ ] No data loss

---

### Phase 2.3: Role Manager (Week 4)

**Goal:** Centralize role and permission management

**Tasks:**
1. Create `class-rfm-expert-role-manager.php`
2. Move role creation logic
3. Extract capability management
4. Move admin access restrictions
5. Implement permission checks
6. Add admin bar management
7. Test access control
8. Verify permissions

**Success Criteria:**
- [ ] Expert role created correctly
- [ ] Capabilities assigned properly
- [ ] Admin access restricted
- [ ] Admin bar hidden for experts
- [ ] Permissions work as expected
- [ ] No security issues

---

### Phase 2.4: Statistics System (Week 5) [Optional]

**Goal:** Add analytics and tracking (if desired)

**Tasks:**
1. Create `class-rfm-dashboard-statistics.php`
2. Create database table
3. Implement tracking methods
4. Add statistics calculation
5. Create caching system
6. Integrate with dashboard
7. Add admin reports
8. Test tracking accuracy

**Success Criteria:**
- [ ] Views tracked correctly
- [ ] Contact clicks recorded
- [ ] Statistics display accurately
- [ ] Caching works
- [ ] Performance acceptable
- [ ] Privacy compliant (GDPR)

---

### Phase 2.5: User Dashboard Optimization (Week 6)

**Goal:** Optimize and cleanup User Dashboard

**Tasks:**
1. Review `class-rfm-user-dashboard.php`
2. Remove duplicate code
3. Optimize database queries
4. Improve JavaScript (`user-dashboard.js`)
5. Add better error handling
6. Implement caching
7. Test performance
8. Verify functionality

**Success Criteria:**
- [ ] Load time improved
- [ ] No duplicate code
- [ ] Queries optimized
- [ ] Better error messages
- [ ] Caching implemented
- [ ] All features work

---

### Phase 2.6: Cleanup & Deprecation (Week 7)

**Goal:** Remove old code and finalize

**Tasks:**
1. Mark `class-rfm-frontend-registration.php` as deprecated
2. Add migration notices
3. Update all class references
4. Remove unused methods
4. Delete deprecated file
5. Update plugin documentation
6. Run full test suite
7. Create changelog

**Success Criteria:**
- [ ] No references to old class
- [ ] All features migrated
- [ ] No broken functionality
- [ ] Tests pass
- [ ] Documentation updated
- [ ] Changelog complete

---

### Phase 2.7: Testing & Release (Week 8)

**Goal:** Comprehensive testing and v3.6.0 release

**Tasks:**
1. Full regression testing
2. Performance benchmarking
3. Security audit
4. User acceptance testing
5. Fix any discovered issues
6. Create v3.6.0 release package
7. Update documentation
8. Deploy to production

**Success Criteria:**
- [ ] All tests pass
- [ ] Performance targets met
- [ ] No security issues
- [ ] User feedback positive
- [ ] Release package ready
- [ ] Documentation complete

---

## 🧪 Testing Strategy

### Unit Tests (PHPUnit)

```php
// tests/test-expert-dashboard.php
class Test_RFM_Expert_Dashboard extends WP_UnitTestCase {
    public function test_dashboard_shortcode_renders() { }
    public function test_statistics_display() { }
    public function test_tab_switching() { }
}

// tests/test-expert-profile-editor.php
class Test_RFM_Expert_Profile_Editor extends WP_UnitTestCase {
    public function test_profile_editor_renders() { }
    public function test_profile_update() { }
    public function test_validation() { }
}

// tests/test-expert-role-manager.php
class Test_RFM_Expert_Role_Manager extends WP_UnitTestCase {
    public function test_role_creation() { }
    public function test_capabilities() { }
    public function test_access_control() { }
}
```

### Integration Tests

1. **Expert Workflow Test**
   - Register → Login → Dashboard → Edit Profile → Logout

2. **Dashboard Feature Test**
   - View statistics → Switch tabs → Update info → Upload image

3. **Permission Test**
   - Expert vs Admin access → Dashboard access → Profile edit

4. **Performance Test**
   - Dashboard load time < 1s
   - Profile save < 500ms
   - Statistics query < 100ms

### Manual Testing Checklist

#### Dashboard Testing
- [ ] Dashboard shortcode renders
- [ ] All tabs display correctly
- [ ] Statistics show accurate data
- [ ] Quick actions work
- [ ] Responsive on mobile
- [ ] No console errors
- [ ] Loading states work

#### Profile Editor Testing
- [ ] Editor form renders
- [ ] All fields editable
- [ ] Validation messages clear
- [ ] Image upload works
- [ ] Category limits enforced
- [ ] Save button works
- [ ] Success message shows
- [ ] Changes persist

#### Role & Permissions Testing
- [ ] Expert role created
- [ ] Experts can't access wp-admin
- [ ] Experts can edit own profile
- [ ] Admin bar hidden for experts
- [ ] Admins retain full access
- [ ] Capabilities correct

#### JavaScript Testing
- [ ] No console errors
- [ ] AJAX calls work
- [ ] Nonces validate
- [ ] Loading indicators show
- [ ] Error messages display
- [ ] Success callbacks fire

---

## 🔧 Technical Considerations

### Backward Compatibility

**Approach:** Maintain old class temporarily

```php
// In rigtig-for-mig.php during transition
if (class_exists('RFM_Frontend_Registration')) {
    // Trigger deprecation notice
    trigger_error(
        'RFM_Frontend_Registration is deprecated. Use modular classes instead.',
        E_USER_DEPRECATED
    );
}
```

### Migration Strategy

**Option 1: Big Bang (Recommended)**
- All changes in single release
- Easier to test
- Clear cutover point
- Less confusion

**Option 2: Gradual**
- Release features incrementally
- Lower risk per release
- More testing time
- More complex coordination

**Recommendation:** Big Bang (Option 1)
- Phase 2 is self-contained
- All changes related
- Easier rollback if needed
- Clearer for users

### Database Changes

**New Table (Optional - for Statistics):**
```sql
CREATE TABLE wp_rfm_statistics (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  expert_id BIGINT UNSIGNED NOT NULL,
  metric_type VARCHAR(50) NOT NULL,
  metric_value BIGINT UNSIGNED DEFAULT 0,
  date DATE NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_expert_date (expert_id, date),
  INDEX idx_metric_type (metric_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

**Migration Script:**
```php
// In class-rfm-database.php
public function create_statistics_table() {
    // Create table if not exists
    // Add initial data if needed
}
```

### Performance Optimization

**Lazy Loading:**
```php
// Only load dashboard class when needed
add_action('wp', function() {
    if (has_shortcode($post->post_content, 'rfm_expert_dashboard')) {
        RFM_Expert_Dashboard::get_instance();
    }
});
```

**Caching Strategy:**
```php
// Cache dashboard statistics
$stats = wp_cache_get("rfm_stats_{$expert_id}", 'rfm_statistics');
if (false === $stats) {
    $stats = $this->calculate_statistics($expert_id);
    wp_cache_set("rfm_stats_{$expert_id}", $stats, 'rfm_statistics', 3600);
}
```

**Query Optimization:**
```php
// Batch load profile data
$profiles = RFM_Expert_Profile::get_multiple($expert_ids);

// Use transients for expensive queries
$popular = get_transient('rfm_popular_experts');
if (false === $popular) {
    $popular = $this->get_popular_experts();
    set_transient('rfm_popular_experts', $popular, DAY_IN_SECONDS);
}
```

---

## ⚠️ Risks & Mitigation

### Risk 1: Breaking Changes
**Likelihood:** Medium
**Impact:** High
**Mitigation:**
- Comprehensive testing
- Staging environment testing
- Gradual rollout option
- Quick rollback plan

### Risk 2: Performance Regression
**Likelihood:** Low
**Impact:** Medium
**Mitigation:**
- Performance benchmarks before/after
- Load testing with realistic data
- Caching implementation
- Query optimization

### Risk 3: Data Loss
**Likelihood:** Very Low
**Impact:** Critical
**Mitigation:**
- Database backups before deployment
- No schema changes to existing tables
- Validation before save
- Audit logging

### Risk 4: User Confusion
**Likelihood:** Low
**Impact:** Low
**Mitigation:**
- No UI changes (same user experience)
- Clear changelog
- Support documentation
- Admin notifications

---

## 📦 Deliverables

### Code Deliverables
1. ✅ `class-rfm-expert-dashboard.php`
2. ✅ `class-rfm-expert-profile-editor.php`
3. ✅ `class-rfm-expert-role-manager.php`
4. ✅ `class-rfm-dashboard-statistics.php` (optional)
5. ✅ `expert-dashboard.js`
6. ✅ `expert-profile-editor.js`
7. ✅ Refactored `class-rfm-user-dashboard.php`
8. ✅ Updated `rigtig-for-mig.php`
9. ✅ `rigtig-for-mig-v3.6.0.zip`

### Documentation Deliverables
1. ✅ `REFACTORING-PHASE-2.md` (completion report)
2. ✅ `CHANGELOG-3.6.0.md`
3. ✅ Updated `RIGTIG-FOR-MIG-CLAUDE-CODE-CONTEXT.md`
4. ✅ API documentation for new classes
5. ✅ Migration guide
6. ✅ Testing documentation

### Testing Deliverables
1. ✅ Unit test suite
2. ✅ Integration test results
3. ✅ Performance benchmarks
4. ✅ Security audit report
5. ✅ User acceptance test results

---

## 💰 Resource Estimation

### Development Time
- **Phase 2.1 (Dashboard):** 16-20 hours
- **Phase 2.2 (Profile Editor):** 12-16 hours
- **Phase 2.3 (Role Manager):** 8-10 hours
- **Phase 2.4 (Statistics):** 12-16 hours (optional)
- **Phase 2.5 (User Dashboard):** 8-10 hours
- **Phase 2.6 (Cleanup):** 6-8 hours
- **Phase 2.7 (Testing):** 10-12 hours

**Total:** 72-92 hours (9-12 days full-time)

### Testing Time
- **Unit testing:** 8 hours
- **Integration testing:** 6 hours
- **Manual testing:** 8 hours
- **Performance testing:** 4 hours

**Total:** 26 hours (3-4 days)

### Grand Total
**98-118 hours (12-15 days full-time)** or **3-4 weeks part-time**

---

## 🎯 Success Metrics

### Code Quality Metrics
- [ ] Average file size < 500 lines
- [ ] Code duplication < 5%
- [ ] All classes follow Single Responsibility
- [ ] 100% of public methods documented
- [ ] No TODO/FIXME comments remain

### Performance Metrics
- [ ] Dashboard load time < 1 second
- [ ] Profile save time < 500ms
- [ ] Memory usage < 32MB per request
- [ ] Database queries < 10 per page load
- [ ] Cache hit rate > 80%

### Testing Metrics
- [ ] Code coverage > 70%
- [ ] All unit tests pass
- [ ] All integration tests pass
- [ ] Zero critical bugs
- [ ] Zero security vulnerabilities

### User Experience Metrics
- [ ] Zero breaking changes for users
- [ ] No additional clicks required
- [ ] Faster page load times
- [ ] Better error messages
- [ ] Improved responsiveness

---

## 🔮 Future Considerations (Phase 3+)

### Potential Future Improvements

1. **User Dashboard Modularization**
   - Split into User Profile Editor
   - User Settings Manager
   - User Notifications

2. **Shortcodes System**
   - Refactor `class-rfm-shortcodes.php`
   - Create individual shortcode classes
   - Better parameter handling

3. **Public Profile Optimization**
   - Separate class for public profiles
   - Better caching
   - Social sharing features

4. **Admin Panel Modularization**
   - Split admin classes
   - Better settings organization
   - Admin dashboard widgets

5. **API Layer**
   - REST API endpoints
   - External integrations
   - Mobile app support

6. **Build System**
   - Asset compilation (CSS/JS)
   - Minification
   - Versioning

---

## 📞 Support & Communication

### Stakeholders
- **Development Team:** Frank H. + Claude Code
- **Testing Team:** [TBD]
- **Product Owner:** [TBD]

### Communication Plan
- **Weekly updates:** Progress reports
- **Blockers:** Immediate notification
- **Decisions:** Document in this file
- **Testing results:** Shared via reports

### Documentation Updates
- Update this file with progress
- Mark completed tasks with ✅
- Document decisions and changes
- Track issues and resolutions

---

## ✅ Pre-Implementation Checklist

Before starting Phase 2 implementation:

- [ ] Phase 1 (v3.5.0) deployed and stable
- [ ] All Phase 1 tests passing
- [ ] Production backup taken
- [ ] Staging environment ready
- [ ] Testing plan approved
- [ ] Resource allocation confirmed
- [ ] Timeline agreed upon
- [ ] Stakeholders notified

---

## 🏁 Conclusion

Phase 2 vil fuldføre refactoring-processen og etablere en moderne, modulær arkitektur for Rigtig for Mig plugin. Med fokus på:

✅ **Modularitet** - Små, fokuserede klasser
✅ **Vedligeholdelse** - Lettere at vedligeholde og udvide
✅ **Performance** - Bedre caching og optimering
✅ **Testbarhed** - Nem at teste og validere
✅ **Skalerbarhed** - Klar til fremtidig vækst

**Ready to Start:** ✅ Planen er klar til godkendelse og implementation!

---

**Document Version:** 1.0
**Created:** 18. december 2025
**Author:** Claude Code + Frank H.
**Status:** ✅ Planning Complete - Awaiting Approval
**Next Step:** Review plan → Get approval → Start Phase 2.1
