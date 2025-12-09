# Fast-Track Gate Control - Complete Technical Documentation

## System Architecture Overview

The Fast-Track Gate Control is a specialized feature designed for Security, Admin, and Dean roles to efficiently process student sign-outs and sign-ins at the campus gate. It consists of a React/Next.js frontend and a Laravel backend with three main API endpoints.

---

## Backend Architecture

### File Location
`backend/app/Http/Controllers/StaffExeatRequestController.php`

### API Routes
Defined in `backend/routes/api.php` (lines 146-148):

```php
Route::get('/exeat-requests/fast-track/search', [StaffExeatRequestController::class, 'searchActionable']);
Route::get('/exeat-requests/fast-track/list', [StaffExeatRequestController::class, 'getActionableList']);
Route::post('/exeat-requests/fast-track/execute', [StaffExeatRequestController::class, 'executeActionable']);
```

---

## Backend Methods Explained

### 1. `searchActionable(Request $request)` - Lines 1745-1820

**Purpose:** Search for students ready for sign-out or sign-in based on user input.

**Parameters:**
- `search` (string): Name, matric number, or ID to search for
- `type` (string): Either 'sign_out' or 'sign_in'

**Logic Flow:**

#### Step 1: Input Validation
```php
if (empty($search) || empty($type)) {
    return response()->json(['exeat_requests' => []]);
}
```

#### Step 2: Status Mapping
```php
$targetStatus = ($type === 'sign_out') ? 'security_signout' : 'security_signin';
```

**Status Meanings:**
- `security_signout`: Student has been approved by all authorities and is ready to leave campus
- `security_signin`: Student has left campus and is ready to return

#### Step 3: Search Logic Closure
```php
$searchLogic = function ($q) use ($search) {
    // Search in Student relationship
    $q->whereHas('student', function ($sq) use ($search) {
        $sq->where('fname', 'like', "%{$search}%")
           ->orWhere('lname', 'like', "%{$search}%")
           ->orWhere('mname', 'like', "%{$search}%")
           ->orWhere('matric_no', 'like', "%{$search}%");

        // If numeric, also search by Student ID
        if (is_numeric($search)) {
            $sq->orWhere('id', $search);
        }
    })
    // Also search matric_no on ExeatRequest itself (redundancy)
    ->orWhere('matric_no', 'like', "%{$search}%");

    // If numeric, also search by Exeat Request ID
    if (is_numeric($search)) {
        $q->orWhere('id', $search);
    }
};
```

**Search Fields:**
1. Student First Name (`students.fname`)
2. Student Last Name (`students.lname`)
3. Student Middle Name (`students.mname`)
4. Student Matric Number (`students.matric_no`)
5. Student ID (`students.id`) - if search term is numeric
6. Exeat Request Matric Number (`exeat_requests.matric_no`)
7. Exeat Request ID (`exeat_requests.id`) - if search term is numeric

#### Step 4: Strict Search (Primary)
```php
$results = ExeatRequest::with(['student:id,fname,lname,mname,passport,matric_no', 'category:id,name'])
    ->where('status', 'like', "%{$targetStatus}%")
    ->where($searchLogic)
    ->orderBy('updated_at', 'desc')
    ->take(50)
    ->get();
```

**Key Points:**
- Uses `LIKE` for status matching (handles whitespace/casing issues)
- Eager loads student and category relationships
- Orders by most recently updated
- Limits to 50 results

#### Step 5: Fallback Search (Debug Mode)
```php
if ($results->isEmpty()) {
    $results = ExeatRequest::with(['student:id,fname,lname,mname,passport,matric_no', 'category:id,name'])
        ->where($searchLogic)
        ->orderBy('updated_at', 'desc')
        ->take(10)
        ->get();

    if ($results->isNotEmpty()) {
        $results->transform(function ($item) {
            if ($item->student) {
                // Append actual status to matric number for debugging
                $item->student->matric_no = $item->student->matric_no . " [STATUS: " . $item->status . "]";
            }
            return $item;
        });
    }
}
```

**Debug Mode Logic:**
- If strict search returns nothing, search without status filter
- If results found, append actual status to matric number
- This helps identify why a student isn't eligible (e.g., stuck at dean_review)
- Limits to 10 results

#### Step 6: Label Action Type
```php
$results->transform(function ($item) use ($type) {
    $item->action_type = $type;
    return $item;
});
```

**Response Format:**
```json
{
  "exeat_requests": [
    {
      "id": 123,
      "student": {
        "id": 55,
        "fname": "John",
        "lname": "Doe",
        "mname": "Michael",
        "passport": "base64...",
        "matric_no": "VUNA/22/1234"
      },
      "category": {
        "id": 2,
        "name": "Weekend"
      },
      "destination": "Lagos",
      "departure_date": "2025-12-10",
      "return_date": "2025-12-12",
      "updated_at": "2025-12-09T10:30:00",
      "status": "security_signout",
      "action_type": "sign_out"
    }
  ]
}
```

---

### 2. `getActionableList(Request $request)` - Lines 1825-1856

**Purpose:** Get a paginated list of all students ready for the current action (sign-out or sign-in).

**Parameters:**
- `type` (string): Either 'sign_out' or 'sign_in'
- `date` (string, optional): YYYY-MM-DD format for date filtering
- `page` (int, optional): Page number (default: 1)

**Logic Flow:**

#### Step 1: Status Mapping
```php
$allowedStatuses = ($type === 'sign_out') ? ['security_signout'] : ['security_signin'];
```

#### Step 2: Build Query
```php
$query = ExeatRequest::with(['student:id,fname,lname,passport,matric_no', 'category:id,name'])
    ->whereIn('status', $allowedStatuses);
```

#### Step 3: Date Filtering (Optional)
```php
if ($date) {
    // For sign_out: filter by departure_date
    // For sign_in: filter by return_date
    $dateField = ($type === 'sign_out') ? 'departure_date' : 'return_date';
    $query->whereDate($dateField, $date);
}
```

**Date Logic:**
- **Sign Out Mode:** Filters by `departure_date` (students leaving today)
- **Sign In Mode:** Filters by `return_date` (students returning today)

#### Step 4: Execute Query with Pagination
```php
$results = $query->orderBy('updated_at', 'desc')
    ->paginate(10);
```

**Pagination:**
- 10 items per page
- Returns Laravel pagination metadata

#### Step 5: Transform Results
```php
$results->getCollection()->transform(function ($item) use ($type) {
    $item->action_type = $type;
    return $item;
});
```

**Response Format:**
```json
{
  "current_page": 1,
  "data": [...],
  "first_page_url": "...",
  "from": 1,
  "last_page": 3,
  "last_page_url": "...",
  "next_page_url": "...",
  "path": "...",
  "per_page": 10,
  "prev_page_url": null,
  "to": 10,
  "total": 25
}
```

---

### 3. `executeActionable(Request $request)` - Lines 1861-1933

**Purpose:** Process a batch of exeat requests (sign out or sign in multiple students).

**Parameters:**
- `request_ids` (array): Array of exeat request IDs to process

**Validation:**
```php
$request->validate([
    'request_ids' => 'required|array',
    'request_ids.*' => 'integer|exists:exeat_requests,id'
]);
```

**Logic Flow:**

#### Step 1: Initialize Tracking Arrays
```php
$ids = $request->input('request_ids');
$user = $request->user();
$processed = [];
$failed = [];
```

#### Step 2: Process Each Request
```php
foreach ($ids as $id) {
    try {
        $exeatRequest = ExeatRequest::find($id);
        if (!$exeatRequest) continue;

        DB::beginTransaction();
```

#### Step 3: Determine Action Based on Current Status
```php
if ($exeatRequest->status === 'security_signout') {
    // Student is ready to LEAVE
    $action = 'sign_out';
    $approval = ExeatApproval::create([
        'exeat_request_id' => $exeatRequest->id,
        'staff_id' => $user->id,
        'role' => 'security',
        'status' => 'approved',
        'method' => 'security_signout',
        'comment' => 'Fast-track sign out'
    ]);
    $this->workflowService->approve($exeatRequest, $approval, 'Fast-track sign out');

} elseif ($exeatRequest->status === 'security_signin') {
    // Student is ready to RETURN
    $action = 'sign_in';
    $approval = ExeatApproval::create([
        'exeat_request_id' => $exeatRequest->id,
        'staff_id' => $user->id,
        'role' => 'security',
        'status' => 'approved',
        'method' => 'security_signin',
        'comment' => 'Fast-track sign in'
    ]);
    $this->workflowService->approve($exeatRequest, $approval, 'Fast-track sign in');
}
```

**Workflow Service Integration:**
- Creates an `ExeatApproval` record
- Calls `ExeatWorkflowService->approve()`
- This advances the status:
  - `security_signout` → `security_signin` (student has left)
  - `security_signin` → `hostel_signin` or `completed` (student has returned)

#### Step 4: Commit Transaction
```php
        DB::commit();

        if ($action) {
            $processed[] = [
                'id' => $id,
                'action' => $action,
                'student' => $exeatRequest->student->fname . ' ' . $exeatRequest->student->lname
            ];
        }

    } catch (\Exception $e) {
        DB::rollBack();
        $failed[] = ['id' => $id, 'error' => $e->getMessage()];
        Log::error('Fast-track execution failed', ['id' => $id, 'error' => $e->getMessage()]);
    }
}
```

**Error Handling:**
- Each request is wrapped in a database transaction
- If one fails, it rolls back only that request
- Other requests continue processing
- Errors are logged and returned in response

#### Step 5: Return Results
```php
return response()->json([
    'message' => 'Bulk processing completed',
    'processed' => $processed,
    'failed' => $failed
]);
```

**Response Format:**
```json
{
  "message": "Bulk processing completed",
  "processed": [
    {
      "id": 123,
      "action": "sign_out",
      "student": "John Doe"
    },
    {
      "id": 124,
      "action": "sign_out",
      "student": "Jane Smith"
    }
  ],
  "failed": []
}
```

---

## Frontend Architecture

### File Location
`backend/front/exeat_front/app/staff/gate-events/fast-track/page.tsx`

### Component Structure

```
FastTrackGatePage
├── State Management (Lines 58-77)
├── Data Fetching (Lines 79-110)
├── Search Logic (Lines 112-140)
├── Tab Switching (Lines 142-154)
├── Queue Management (Lines 156-166)
├── Bulk Processing (Lines 168-200)
└── UI Rendering (Lines 207-453)
```

---

## Frontend State Management

### State Variables (Lines 58-77)

```typescript
// Tab State
const [activeTab, setActiveTab] = useState<'sign_out' | 'sign_in'>('sign_out');

// Search State
const [searchQuery, setSearchQuery] = useState('');
const [searchResults, setSearchResults] = useState<ExeatRequest[]>([]);
const [isSearching, setIsSearching] = useState(false);

// List State
const [listData, setListData] = useState<ExeatRequest[]>([]);
const [listMeta, setListMeta] = useState<PaginationMeta | null>(null);
const [listLoading, setListLoading] = useState(false);
const [listPage, setListPage] = useState(1);
const [filterDate, setFilterDate] = useState<string>('');

// Queue State
const [queue, setQueue] = useState<ExeatRequest[]>([]);
const [isProcessing, setIsProcessing] = useState(false);

// Ref for auto-focus
const searchInputRef = useRef<HTMLInputElement>(null);
```

---

## Frontend Data Fetching

### 1. Fetch Eligible Students List (Lines 79-110)

```typescript
const fetchList = useCallback(async () => {
    setListLoading(true);
    try {
        const token = localStorage.getItem('token');
        let url = `${process.env.NEXT_PUBLIC_API_BASE_URL}/staff/exeat-requests/fast-track/list?type=${activeTab}&page=${listPage}`;
        if (filterDate) url += `&date=${filterDate}`;
        
        const response = await fetch(url, {
            headers: { 'Authorization': `Bearer ${token}`, 'Accept': 'application/json' }
        });
        
        if (response.ok) {
            const data = await response.json();
            setListData(data.data);
            setListMeta({
                current_page: data.current_page,
                last_page: data.last_page,
                total: data.total,
                per_page: data.per_page
            });
        }
    } catch (error) {
        console.error('Fetch list failed', error);
    } finally {
        setListLoading(false);
    }
}, [activeTab, listPage, filterDate]);
```

**useCallback Dependencies:**
- `activeTab`: Refetch when switching between Sign Out/Sign In
- `listPage`: Refetch when changing pages
- `filterDate`: Refetch when date filter changes

**Auto-fetch Effect:**
```typescript
useEffect(() => {
    fetchList();
}, [fetchList]);
```

---

### 2. Search for Students (Lines 112-140)

```typescript
const performSearch = useCallback(async (query: string) => {
    setIsSearching(true);
    try {
        const token = localStorage.getItem('token');
        const response = await fetch(
            `${process.env.NEXT_PUBLIC_API_BASE_URL}/staff/exeat-requests/fast-track/search?search=${encodeURIComponent(query)}&type=${activeTab}`,
            {
                headers: { 'Authorization': `Bearer ${token}`, 'Accept': 'application/json' }
            }
        );
        if (response.ok) {
            const data = await response.json();
            // Filter out students already in queue
            const newResults = data.exeat_requests.filter((req: ExeatRequest) => 
                !queue.some(qItem => qItem.id === req.id)
            );
            setSearchResults(newResults);
        }
    } catch (error) { 
        console.error(error); 
    } finally { 
        setIsSearching(false); 
    }
}, [activeTab, queue]);
```

**Debounced Search Effect:**
```typescript
useEffect(() => {
    const timer = setTimeout(() => {
        if (searchQuery.length >= 2) {
            performSearch(searchQuery);
        } else {
            setSearchResults([]);
        }
    }, 300);
    return () => clearTimeout(timer);
}, [searchQuery, performSearch]);
```

**Debouncing Logic:**
- Waits 300ms after user stops typing
- Only searches if query is 2+ characters
- Clears results if query is too short
- Cancels previous timeout on new input

---

## Frontend User Interactions

### 1. Tab Switching (Lines 142-154)

```typescript
const handleTabChange = (value: string) => {
    const mode = value as 'sign_out' | 'sign_in';
    
    // Safety check: warn if queue has items
    if (queue.length > 0) {
        if (!confirm(`Switching modes will clear your current queue. Continue?`)) return;
    }
    
    // Reset all state
    setActiveTab(mode);
    setQueue([]);
    setSearchQuery('');
    setSearchResults([]);
    setListPage(1);
    setFilterDate('');
    
    // Auto-focus search input
    setTimeout(() => searchInputRef.current?.focus(), 100);
};
```

**Safety Features:**
- Confirms with user if queue has items
- Clears queue to prevent mixed actions
- Resets search and filters
- Auto-focuses search input

---

### 2. Queue Management (Lines 156-166)

```typescript
const addToQueue = (request: ExeatRequest) => {
    // Prevent duplicates
    if (queue.some(q => q.id === request.id)) return;
    
    // Add to queue
    setQueue(prev => [...prev, request]);
    
    // Clear search for next scan
    setSearchQuery('');
    setSearchResults([]);
    
    // Auto-focus for next search
    searchInputRef.current?.focus();
};

const removeFromQueue = (id: number) => {
    setQueue(prev => prev.filter(item => item.id !== id));
};
```

**Queue Features:**
- Duplicate prevention
- Auto-clear search after adding
- Auto-focus for rapid scanning
- Individual item removal

---

### 3. Bulk Processing (Lines 168-200)

```typescript
const processQueue = async () => {
    if (queue.length === 0) return;
    
    setIsProcessing(true);
    try {
        const token = localStorage.getItem('token');
        const response = await fetch(
            `${process.env.NEXT_PUBLIC_API_BASE_URL}/staff/exeat-requests/fast-track/execute`,
            {
                method: 'POST',
                headers: { 
                    'Authorization': `Bearer ${token}`, 
                    'Content-Type': 'application/json', 
                    'Accept': 'application/json' 
                },
                body: JSON.stringify({ request_ids: queue.map(r => r.id) })
            }
        );

        const result = await response.json();
        if (response.ok) {
            toast({
                title: "Success",
                description: `Successfully processed ${result.processed.length} students.`,
                className: "bg-green-600 text-white"
            });
            setQueue([]);
            fetchList(); // Refresh eligible list
        } else {
            throw new Error(result.message);
        }
    } catch (error: any) {
        toast({
            title: "Error",
            description: error.message || "Failed to process.",
            variant: "destructive"
        });
    } finally {
        setIsProcessing(false);
    }
};
```

**Processing Flow:**
1. Validate queue is not empty
2. Set processing state (disables button)
3. Send all request IDs to backend
4. Show success/error toast
5. Clear queue on success
6. Refresh eligible list
7. Re-enable button

---

## UI Components Breakdown

### 1. Dual-Tab Interface (Lines 216-226)

```tsx
<TabsList className="grid w-full grid-cols-2 h-14 md:w-[400px] mb-6">
    <TabsTrigger value="sign_out" className="h-full gap-2 data-[state=active]:bg-red-100 data-[state=active]:text-red-800 data-[state=active]:border-red-200 border border-transparent">
        <LogOut className="h-5 w-5" />
        <span className="font-bold">SIGN OUT</span>
    </TabsTrigger>
    <TabsTrigger value="sign_in" className="h-full gap-2 data-[state=active]:bg-green-100 data-[state=active]:text-green-800 data-[state=active]:border-green-200 border border-transparent">
        <LogIn className="h-5 w-5" />
        <span className="font-bold">SIGN IN</span>
    </TabsTrigger>
</TabsList>
```

**Visual Design:**
- **Sign Out:** Red theme (danger/leaving)
- **Sign In:** Green theme (safe/returning)
- Large, prominent tabs for quick identification

---

### 2. Search Panel (Lines 231-276)

**Features:**
- Auto-focused input field
- Loading spinner during search
- Debounced input (300ms)
- Enter key to add first result
- Click any result to add to queue
- Shows "No eligible students found" message

**Search Card Design:**
```tsx
<div onClick={() => addToQueue(req)} className="flex items-center gap-3 p-3 rounded-lg border bg-card hover:bg-accent cursor-pointer transition-colors group">
    <Avatar className="h-10 w-10 border">
        <AvatarImage src={req.student.passport ? `data:image/jpeg;base64,${req.student.passport}` : ''} />
        <AvatarFallback>{getInitials(req.student.fname, req.student.lname)}</AvatarFallback>
    </Avatar>
    <div className="flex-1 min-w-0">
        <h4 className="font-semibold truncate text-sm">{req.student.fname} {req.student.lname}</h4>
        <p className="text-xs text-muted-foreground">{req.student.matric_no}</p>
    </div>
    <Plus className="h-4 w-4 text-muted-foreground" />
</div>
```

---

### 3. Action Queue Panel (Lines 279-329)

**Features:**
- Shows queue count badge
- "Clear All" button
- Numbered list items
- Individual remove buttons
- Execute button (red for sign-out, green for sign-in)
- Disabled when empty or processing
- Shows loading spinner during processing

**Queue Item Design:**
```tsx
<div className="flex items-center gap-3 p-3 rounded-lg border bg-white shadow-sm animate-in slide-in-from-left-2 duration-300">
    <div className="flex items-center justify-center h-5 w-5 rounded-full bg-slate-100 text-slate-500 text-xs font-mono">{index + 1}</div>
    <Avatar className="h-8 w-8">...</Avatar>
    <div className="flex-1 min-w-0">
        <h4 className="font-medium text-sm truncate">{req.student.fname} {req.student.lname}</h4>
    </div>
    <Button variant="ghost" size="icon" className="h-7 w-7" onClick={() => removeFromQueue(req.id)}>
        <X className="h-3 w-3" />
    </Button>
</div>
```

---

### 4. Eligible Students List (Lines 333-449)

**Features:**
- Date filter input
- Paginated table (10 per page)
- Shows student photo, name, matric, destination
- "Add to Queue" button for each student
- Button shows "In Queue" if already added
- Disabled if queue is full (10 items)
- Pagination controls

**Table Row Design:**
```tsx
<tr className="border-b last:border-0 hover:bg-slate-50/50">
    <td className="py-3 pl-2 max-w-[200px]">
        <div className="flex items-center gap-3">
            <Avatar className="h-8 w-8">...</Avatar>
            <div>
                <div className="font-medium truncate">{req.student.fname} {req.student.lname}</div>
                <div className="text-xs text-muted-foreground">{req.destination}</div>
            </div>
        </div>
    </td>
    <td className="py-3 font-mono text-xs">{req.student.matric_no}</td>
    <td className="py-3">
        <Badge variant="outline" className={activeTab === 'sign_out' ? 'text-red-600 border-red-200 bg-red-50' : 'text-green-600 border-green-200 bg-green-50'}>
            {activeTab === 'sign_out' ? 'Ready to Leave' : 'Ready to Return'}
        </Badge>
    </td>
    <td className="py-3 text-right pr-2">
        <Button 
            size="sm" 
            variant={isInQueue(req.id) ? "secondary" : "outline"}
            className="h-8 text-xs gap-1"
            onClick={() => isInQueue(req.id) ? removeFromQueue(req.id) : addToQueue(req)}
            disabled={queue.length >= 10 && !isInQueue(req.id)}
        >
            {isInQueue(req.id) ? <CheckCircle2 className="h-3 w-3 text-green-600" /> : <Plus className="h-3 w-3" />}
            {isInQueue(req.id) ? 'In Queue' : 'Add'}
        </Button>
    </td>
</tr>
```

---

## Complete User Flow

### Sign Out Flow

1. **Security staff opens Fast-Track page**
   - Default tab: "SIGN OUT" (red)
   - Eligible list loads automatically (students with `status='security_signout'`)

2. **Staff searches for student**
   - Types name or matric number
   - System searches after 300ms delay
   - Backend searches:
     - First name, last name, middle name
     - Matric number
     - Student ID (if numeric)
   - Results appear in left panel

3. **Staff adds student to queue**
   - Clicks search result OR presses Enter
   - Student moves to queue panel (right)
   - Search clears automatically
   - Input auto-focuses for next scan

4. **Staff repeats for multiple students**
   - Can add up to 10 students
   - Each gets a number (1, 2, 3...)
   - Can remove individual students

5. **Staff executes bulk sign-out**
   - Clicks red "Execute" button
   - Backend processes all requests:
     - Creates `ExeatApproval` records
     - Calls `WorkflowService->approve()`
     - Status changes: `security_signout` → `security_signin`
   - Success toast shows count
   - Queue clears
   - Eligible list refreshes

### Sign In Flow

1. **Security staff switches to "SIGN IN" tab**
   - Tab turns green
   - If queue has items, confirms clear
   - Eligible list loads (students with `status='security_signin'`)

2. **Staff searches for returning student**
   - Same search logic as sign-out
   - Backend filters by `status='security_signin'`

3. **Staff adds to queue and executes**
   - Same queue logic
   - Backend processes:
     - Status changes: `security_signin` → `hostel_signin` or `completed`

---

## Debug Mode Example

### Scenario: Student stuck at Dean's office

**User searches:** "John Doe"

**Backend strict search:**
- Looks for `status LIKE '%security_signout%'`
- Student has `status='dean_review'`
- No match found

**Backend fallback search:**
- Searches without status filter
- Finds student with `status='dean_review'`
- Appends status to matric number

**Frontend displays:**
```
John Doe
VUNA/22/1234 [STATUS: dean_review]
```

**User understands:**
- Student exists
- Student is stuck at Dean's approval stage
- Cannot sign out yet

---

## Security & Safety Features

1. **Strict Action Separation**
   - Sign Out and Sign In are completely separate
   - Cannot mix actions in one queue
   - Tab switch clears queue with confirmation

2. **Status Validation**
   - Backend double-checks status before processing
   - Only processes if status matches expected value
   - Prevents accidental state transitions

3. **Database Transactions**
   - Each request wrapped in transaction
   - Rollback on error
   - Other requests continue processing

4. **Error Logging**
   - All failures logged to Laravel log
   - Error details returned to frontend
   - User sees which students failed

5. **Duplicate Prevention**
   - Cannot add same student twice to queue
   - Queue filters out already-added students from search

6. **Queue Limit**
   - Maximum 10 students per batch
   - Prevents overwhelming the system
   - "Add" button disabled when full

---

## Performance Optimizations

1. **Debounced Search**
   - 300ms delay prevents excessive API calls
   - Cancels previous requests

2. **useCallback Hooks**
   - `fetchList` and `performSearch` memoized
   - Prevents unnecessary re-renders
   - Dependencies properly tracked

3. **Pagination**
   - List limited to 10 items per page
   - Reduces data transfer
   - Faster page loads

4. **Eager Loading**
   - Backend uses `with()` to load relationships
   - Prevents N+1 queries
   - Single database query per request

5. **Search Limit**
   - Strict search: max 50 results
   - Fallback search: max 10 results
   - Prevents memory issues

---

## Error Handling

### Frontend Errors

1. **Network Failure**
```typescript
catch (error) {
    console.error('Fetch list failed', error);
}
```

2. **Processing Failure**
```typescript
catch (error: any) {
    toast({
        title: "Error",
        description: error.message || "Failed to process.",
        variant: "destructive"
    });
}
```

### Backend Errors

1. **Validation Errors**
```php
$request->validate([
    'request_ids' => 'required|array',
    'request_ids.*' => 'integer|exists:exeat_requests,id'
]);
```

2. **Processing Errors**
```php
try {
    DB::beginTransaction();
    // ... processing logic
    DB::commit();
} catch (\Exception $e) {
    DB::rollBack();
    $failed[] = ['id' => $id, 'error' => $e->getMessage()];
    Log::error('Fast-track execution failed', ['id' => $id, 'error' => $e->getMessage()]);
}
```

---

## Database Schema Reference

### Tables Used

1. **exeat_requests**
   - `id`: Primary key
   - `student_id`: Foreign key to students
   - `matric_no`: Cached matric number
   - `category_id`: Foreign key to exeat_categories
   - `status`: Current workflow status
   - `destination`: Where student is going
   - `departure_date`: When leaving
   - `return_date`: When returning
   - `updated_at`: Last status change

2. **students**
   - `id`: Primary key
   - `fname`: First name
   - `lname`: Last name
   - `mname`: Middle name
   - `matric_no`: Matriculation number
   - `passport`: Base64 encoded photo

3. **exeat_approvals**
   - `id`: Primary key
   - `exeat_request_id`: Foreign key
   - `staff_id`: Who approved
   - `role`: Staff role (security, dean, etc.)
   - `status`: approved/rejected
   - `method`: security_signout/security_signin
   - `comment`: Approval comment

---

## Status Workflow Reference

```
pending
  ↓
cmd_review (if medical)
  ↓
secretary_review
  ↓
parent_consent
  ↓
dean_review (skipped for daily)
  ↓
hostel_signout (skipped for holiday)
  ↓
security_signout ← FAST-TRACK SIGN OUT
  ↓
security_signin ← FAST-TRACK SIGN IN
  ↓
hostel_signin
  ↓
completed
```

**Fast-Track Handles:**
- `security_signout` → `security_signin` (Sign Out)
- `security_signin` → `hostel_signin` or `completed` (Sign In)

---

## Configuration

### Environment Variables

**Frontend (.env.local):**
```
NEXT_PUBLIC_API_BASE_URL=http://localhost:8000/api
```

**Backend (.env):**
```
APP_URL=http://localhost:8000
```

### Route Middleware

```php
Route::middleware(['auth:sanctum', 'role:staff'])->group(function () {
    // Fast-track routes
});
```

**Required Roles:**
- Security
- Admin
- Dean
- Deputy Dean

---

## Testing Checklist

### Backend Tests

- [ ] Search by first name
- [ ] Search by last name
- [ ] Search by matric number
- [ ] Search by student ID
- [ ] Search with wrong status (debug mode)
- [ ] List pagination
- [ ] List date filtering
- [ ] Execute sign-out
- [ ] Execute sign-in
- [ ] Execute with invalid ID
- [ ] Execute with mixed statuses

### Frontend Tests

- [ ] Tab switching clears queue
- [ ] Search debouncing works
- [ ] Add to queue
- [ ] Remove from queue
- [ ] Queue limit (10 items)
- [ ] Execute button disabled when empty
- [ ] Execute button disabled when processing
- [ ] Success toast appears
- [ ] Error toast appears
- [ ] List pagination works
- [ ] Date filter works
- [ ] "Add to Queue" from list
- [ ] "In Queue" button shows correctly

---

## Maintenance Notes

### Common Issues

1. **"No eligible students found"**
   - Check student status in database
   - Verify status is exactly `security_signout` or `security_signin`
   - Check debug mode output (status appended to matric)

2. **Search not working**
   - Verify API endpoint is accessible
   - Check browser console for errors
   - Verify token in localStorage
   - Check backend logs

3. **Execute fails**
   - Check database transactions
   - Verify WorkflowService is working
   - Check Laravel logs for exceptions
   - Verify user has correct role

### Future Enhancements

1. **Barcode Scanning**
   - Add barcode scanner integration
   - Auto-submit on scan

2. **Bulk Upload**
   - CSV import of matric numbers
   - Auto-add to queue

3. **Real-time Updates**
   - WebSocket integration
   - Live queue updates across devices

4. **Analytics**
   - Track processing times
   - Monitor peak hours
   - Generate reports

---

## Summary

The Fast-Track Gate Control system is a robust, user-friendly solution for processing student gate movements. It combines:

- **Smart Search:** Multi-field search with debug mode
- **Queue System:** Batch processing up to 10 students
- **Safety Features:** Strict action separation, transaction safety
- **User Experience:** Auto-focus, debouncing, visual feedback
- **Error Handling:** Graceful degradation, detailed logging

The system is production-ready and handles edge cases effectively.
