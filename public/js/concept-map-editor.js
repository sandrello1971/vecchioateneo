/**
 * Concept Map Editor — wrapper vanilla di vis-network (UMD via CDN)
 * Esposto come window.NosciteConceptMap.{createEditor, createViewer}
 *
 * Richiede che window.vis (vis-network UMD) sia gia' caricato.
 *
 * Uso:
 *   const editor = NosciteConceptMap.createEditor('#cm-canvas', initialData, {
 *     onChange: (data) => { ... },     // chiamato a ogni modifica
 *     onSelect: (node) => { ... },     // nodo cliccato (può essere null)
 *   });
 *   editor.getData() => { nodes:[...], edges:[...] }
 *   editor.addNode({label,description,...})
 *   editor.updateNode(id, patch)
 *   editor.removeNode(id)
 *   editor.startAddEdgeMode()
 *   editor.replaceData({nodes,edges})
 *   editor.fit()
 */
(function () {
    if (!window.vis || !window.vis.Network) {
        console.warn('NosciteConceptMap: window.vis non disponibile. Includere vis-network UMD prima di questo script.');
        return;
    }

    var PALETTE = {
        teal: '#55B1AE',
        tealDark: '#3D8B88',
        tealLight: '#E8F5F5',
        orange: '#E28A53',
        grayBorder: '#D1D5DB',
        text: '#1F2937',
    };

    function buildOptions(editable) {
        return {
            nodes: {
                shape: 'box',
                margin: { top: 10, right: 14, bottom: 10, left: 14 },
                font: { face: 'Calibri, Segoe UI, sans-serif', size: 14, color: PALETTE.text, multi: false },
                color: {
                    background: PALETTE.tealLight,
                    border: PALETTE.teal,
                    highlight: { background: '#FFFFFF', border: PALETTE.tealDark },
                    hover: { background: '#FFFFFF', border: PALETTE.tealDark },
                },
                borderWidth: 2,
                borderWidthSelected: 3,
                chosen: true,
                widthConstraint: { minimum: 80, maximum: 200 },
            },
            edges: {
                font: { face: 'Calibri, Segoe UI, sans-serif', size: 11, color: PALETTE.tealDark, background: 'rgba(255,255,255,0.85)', strokeWidth: 0, align: 'middle' },
                color: { color: PALETTE.teal, hover: PALETTE.tealDark, highlight: PALETTE.orange },
                arrows: { to: { enabled: true, scaleFactor: 0.7 } },
                smooth: { type: 'continuous', roundness: 0.2 },
            },
            physics: {
                enabled: true,
                barnesHut: { gravitationalConstant: -8000, springConstant: 0.04, springLength: 140 },
                stabilization: { iterations: 200, fit: true },
            },
            interaction: {
                hover: true,
                tooltipDelay: 200,
                navigationButtons: false,
                keyboard: editable,
                dragNodes: editable,
                dragView: true,
                zoomView: true,
                selectConnectedEdges: false,
            },
            manipulation: editable ? {
                enabled: true,
                initiallyActive: false,
                addNode: false,
                addEdge: function (data, callback) {
                    var label = window.prompt('Etichetta della relazione (es. "richiede", "include", "causa"):', '');
                    if (!label || !label.trim()) return;
                    data.id = 'e' + Date.now();
                    data.label = label.trim().substring(0, 60);
                    data.arrows = 'to';
                    callback(data);
                },
                editEdge: false,
                deleteNode: true,
                deleteEdge: true,
            } : { enabled: false },
            layout: { improvedLayout: true },
        };
    }

    function normalizeGraph(data) {
        var safe = data && typeof data === 'object' ? data : {};
        var nodes = Array.isArray(safe.nodes) ? safe.nodes : [];
        var edges = Array.isArray(safe.edges) ? safe.edges : [];
        return {
            nodes: nodes.map(function (n) {
                return {
                    id: String(n.id),
                    label: String(n.label || '').substring(0, 120),
                    description: n.description || '',
                    title: n.description || undefined,
                    link_type: n.link_type || null,
                    link_module_id: n.link_module_id || null,
                    link_material_id: n.link_material_id || null,
                    link_url: n.link_url || null,
                    x: typeof n.x === 'number' ? n.x : undefined,
                    y: typeof n.y === 'number' ? n.y : undefined,
                };
            }),
            edges: edges.map(function (e) {
                return {
                    id: String(e.id),
                    from: String(e.from),
                    to: String(e.to),
                    label: String(e.label || '').substring(0, 60),
                    arrows: e.arrows || 'to',
                };
            }),
        };
    }

    function createFactory(containerSel, initialData, opts) {
        opts = opts || {};
        var container = typeof containerSel === 'string'
            ? document.querySelector(containerSel)
            : containerSel;
        if (!container) {
            throw new Error('Concept map container not found: ' + containerSel);
        }

        var editable = opts.editable !== false;
        var graph = normalizeGraph(initialData);

        var nodesDS = new window.vis.DataSet(graph.nodes);
        var edgesDS = new window.vis.DataSet(graph.edges);
        var network = new window.vis.Network(container, { nodes: nodesDS, edges: edgesDS }, buildOptions(editable));

        // Fit iniziale + auto-fit dopo stabilizzazione physics + auto-fit su resize
        function safeFit() {
            try { network.fit({ animation: { duration: 400, easingFunction: 'easeOutQuad' } }); } catch (e) {}
        }
        network.once('stabilizationIterationsDone', function () {
            // Una volta stabilizzato, spegni physics per non far ri-balzare i nodi a ogni edit
            try { network.setOptions({ physics: { enabled: false } }); } catch (e) {}
            safeFit();
        });
        // Backup: alcune browser/layout non triggerano stabilizationIterationsDone
        // se il container ha ancora dimensione 0 all'init.
        setTimeout(safeFit, 250);
        setTimeout(safeFit, 800);

        if (typeof ResizeObserver !== 'undefined') {
            var ro = new ResizeObserver(function () {
                try { network.redraw(); } catch (e) {}
            });
            ro.observe(container);
        }

        function getData() {
            var positions = network.getPositions();
            var nodes = nodesDS.get().map(function (n) {
                var pos = positions[n.id] || {};
                return {
                    id: n.id,
                    label: n.label,
                    description: n.description || '',
                    link_type: n.link_type || null,
                    link_module_id: n.link_module_id || null,
                    link_material_id: n.link_material_id || null,
                    link_url: n.link_url || null,
                    x: typeof pos.x === 'number' ? Math.round(pos.x) : undefined,
                    y: typeof pos.y === 'number' ? Math.round(pos.y) : undefined,
                };
            });
            var edges = edgesDS.get().map(function (e) {
                return { id: e.id, from: e.from, to: e.to, label: e.label || '', arrows: 'to' };
            });
            return { nodes: nodes, edges: edges, physics: { enabled: false } };
        }

        function emitChange() {
            if (typeof opts.onChange === 'function') opts.onChange(getData());
        }

        if (editable) {
            nodesDS.on('*', emitChange);
            edgesDS.on('*', emitChange);
        }

        network.on('selectNode', function (params) {
            if (typeof opts.onSelect === 'function') {
                var id = params.nodes[0];
                var node = id ? nodesDS.get(id) : null;
                opts.onSelect(node);
            }
        });

        network.on('deselectNode', function () {
            if (typeof opts.onSelect === 'function') opts.onSelect(null);
        });

        network.on('click', function (params) {
            if (!editable && params.nodes.length === 1) {
                var id = params.nodes[0];
                var n = nodesDS.get(id);
                if (n && typeof opts.onNodeClick === 'function') {
                    opts.onNodeClick(n);
                }
            }
        });

        return {
            network: network,
            nodesDS: nodesDS,
            edgesDS: edgesDS,
            getData: getData,
            addNode: function (attrs) {
                attrs = attrs || {};
                var id = attrs.id || 'n' + Date.now();
                nodesDS.add({
                    id: id,
                    label: (attrs.label || 'Nuovo concetto').substring(0, 120),
                    description: attrs.description || '',
                    title: attrs.description || undefined,
                    link_type: attrs.link_type || null,
                    link_module_id: attrs.link_module_id || null,
                    link_material_id: attrs.link_material_id || null,
                    link_url: attrs.link_url || null,
                });
                return id;
            },
            updateNode: function (id, patch) {
                var existing = nodesDS.get(id);
                if (!existing) return;
                var next = Object.assign({}, existing, patch);
                if (patch.description !== undefined) {
                    next.title = patch.description || undefined;
                }
                nodesDS.update(next);
            },
            removeNode: function (id) {
                var connected = edgesDS.get({ filter: function (e) { return e.from === id || e.to === id; } }).map(function (e) { return e.id; });
                if (connected.length) edgesDS.remove(connected);
                nodesDS.remove(id);
            },
            startAddEdgeMode: function () { network.addEdgeMode(); },
            removeSelected: function () {
                network.deleteSelected();
                emitChange();
            },
            replaceData: function (newData) {
                var g = normalizeGraph(newData);
                nodesDS.clear();
                edgesDS.clear();
                nodesDS.add(g.nodes);
                edgesDS.add(g.edges);
                network.fit();
            },
            fit: function () { network.fit(); },
            destroy: function () { network.destroy(); },
        };
    }

    window.NosciteConceptMap = {
        createEditor: function (container, initialData, opts) {
            opts = opts || {};
            opts.editable = true;
            return createFactory(container, initialData, opts);
        },
        createViewer: function (container, initialData, opts) {
            opts = opts || {};
            opts.editable = false;
            return createFactory(container, initialData, opts);
        },
    };
})();
