-- Migration 014: Reusable widget library
-- Small curated table of pre-validated tm-* widget payloads for canonical,
-- non-personalized topics (e.g. a specific algorithm trace, a math manipulative).
-- The AI is shown title+topic_key (never the full payload, to keep prompt size
-- small) and can reference an entry with a tm-ref block instead of re-authoring
-- the JSON from scratch. Resolved server-side by
-- api/services/widget_library_service.php::resolveWidgetLibraryRefs().

CREATE TABLE IF NOT EXISTS widget_library (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    topic_key    VARCHAR(100) NOT NULL UNIQUE,
    title        VARCHAR(200) NOT NULL,
    widget_type  ENUM('graph','steps','code','check','order','cloze') NOT NULL,
    payload      JSON NOT NULL,
    created_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    usage_count  INT NOT NULL DEFAULT 0,
    INDEX idx_widget_type (widget_type)
);

-- Seed 1: Dijkstra's algorithm on the same 5-node graph already used in
-- tm_widget_test.php (A-B-C-D-E), converted to the delta schema — the graph's
-- node positions/edges are defined once, each step's visual carries only the
-- highlight/dist delta.
INSERT IGNORE INTO widget_library (topic_key, title, widget_type, payload) VALUES (
    'dijkstra-basic-graph-a-to-e',
    'Dijkstra''s algorithm, 5-node graph, shortest path A to E',
    'steps',
    '{"q": "Shortest path from A to E", "predict": true, "steps": [{"text": "Start: dist(A)=0, everything else infinity. Visit A, relax its edges: C becomes 1, B becomes 4.", "visual": {"highlight": {"nodes": ["A"], "edges": [["A","B"],["A","C"]]}, "dist": {"A": "0", "B": "4", "C": "1"}}}, {"text": "Smallest unvisited is C (1). Relax from C: B via C is 1+2=3 (better than 4, so B becomes 3); D via C is 1+5=6.", "visual": {"highlight": {"nodes": ["C"], "edges": [["C","B"],["C","D"]]}, "dist": {"A": "0", "B": "3", "C": "1", "D": "6"}}}, {"text": "Smallest unvisited is B (3). Relax from B: D via B is 3+1=4 (better than 6, so D becomes 4).", "visual": {"highlight": {"nodes": ["B"], "edges": [["B","D"]]}, "dist": {"A": "0", "B": "3", "C": "1", "D": "4"}}}, {"text": "Smallest unvisited is D (4). Relax from D: E is 4+3=7.", "visual": {"highlight": {"nodes": ["D"], "edges": [["D","E"]]}, "dist": {"A": "0", "B": "3", "C": "1", "D": "4", "E": "7"}}}, {"text": "Visit E (7). Done — shortest A to E is 7, via A - C - B - D - E.", "visual": {"highlight": {"nodes": ["A","C","B","D","E"], "edges": [["A","C"],["C","B"],["B","D"],["D","E"]]}, "dist": {"A": "0", "B": "3", "C": "1", "D": "4", "E": "7"}}}], "graph": {"nodes": [{"id": "A", "x": 20, "y": 80, "label": "A"}, {"id": "B", "x": 90, "y": 20, "label": "B"}, {"id": "C", "x": 90, "y": 140, "label": "C"}, {"id": "D", "x": 160, "y": 80, "label": "D"}, {"id": "E", "x": 210, "y": 40, "label": "E"}], "edges": [{"from": "A", "to": "B", "weight": "4"}, {"from": "A", "to": "C", "weight": "1"}, {"from": "C", "to": "B", "weight": "2"}, {"from": "C", "to": "D", "weight": "5"}, {"from": "B", "to": "D", "weight": "1"}, {"from": "D", "to": "E", "weight": "3"}]}}'
);

-- Seed 2: sine wave amplitude/frequency manipulative.
INSERT IGNORE INTO widget_library (topic_key, title, widget_type, payload) VALUES (
    'sine-wave-amplitude-frequency',
    'Sine wave: how amplitude and frequency shape the curve',
    'graph',
    '{"q": "How does the amplitude and frequency of a sine wave change its shape?", "template": "sine", "params": [{"key": "a", "label": "Amplitude (a)", "min": -3, "max": 3, "step": 0.5, "default": 1}, {"key": "b", "label": "Frequency (b)", "min": 0.5, "max": 4, "step": 0.5, "default": 1}], "domain": [-6.28, 6.28], "range": [-4, 4], "task": "Drag each slider on its own — what does each one change about the wave?"}'
);
