let cy;

        // Authentication credentials (can be set via browser prompt)
        // For production, implement proper authentication management
        let authCredentials = null;

        // Get auth credentials (prompt user if not set)
        function getAuthHeader() {
            if (!authCredentials) {
                const username = prompt('Enter username (default: admin):', 'admin');
                if (!username) return null;

                const password = prompt('Enter password (default: password):', 'password');
                if (!password) return null;

                authCredentials = btoa(username + ':' + password);
            }
            return 'Basic ' + authCredentials;
        }

        // Category shape mapping
        const categoryShapes = {
            'business': 'ellipse',
            'application': 'round-rectangle',
            'infrastructure': 'diamond'
        };

        // Category color mapping (fallback when no status)
        const categoryColors = {
            'business': '#007bff',      // blue
            'application': '#28a745',   // green
            'infrastructure': '#fd7e14', // orange
            'default': '#6c757d'
        };

        // Status color mapping (takes precedence over category)
        const statusColors = {
            'healthy': '#28a745',     // green
            'unhealthy': '#dc3545',   // red
            'maintenance': '#ffc107', // yellow
            'unknown': '#6c757d'      // gray
        };

        // Query nodes by category
        function getNodesByCategory(category) {
            if (!cy) return [];
            if (category === 'all') {
                return cy.nodes();
            }
            return cy.nodes().filter(node => {
                const nodeCategory = node.data('category');
                return nodeCategory === category;
            });
        }

        // Get all unique categories from nodes
        function getAllCategories() {
            if (!cy) return [];
            const categories = new Set();
            cy.nodes().forEach(node => {
                const category = node.data('category');
                if (category) {
                    categories.add(category);
                }
            });
            return Array.from(categories).sort();
        }

        // Get category color
        function getCategoryColor(category) {
            return categoryColors[category] || categoryColors['default'];
        }

        // Get status color
        function getStatusColor(status) {
            return statusColors[status] || statusColors['unknown'];
        }

        // Get node color (prioritize status over category)
        function getNodeColor(node) {
            const status = node.data('status');
            const category = node.data('category');

            // If status exists, use it; otherwise fall back to category
            if (status && status !== 'unknown') {
                return getStatusColor(status);
            }
            return getCategoryColor(category);
        }

        // Filter nodes by category
        function filterByCategory() {
            const selectedCategory = document.getElementById('categoryFilter').value;

            if (selectedCategory === 'all') {
                cy.nodes().style('display', 'element');
            } else {
                cy.nodes().forEach(node => {
                    const nodeCategory = node.data('category');
                    if (nodeCategory === selectedCategory) {
                        node.style('display', 'element');
                    } else {
                        node.style('display', 'none');
                    }
                });
            }

            cy.fit();
        }

        // Update category filter dropdown and legend
        function updateCategoryUI() {
            const categories = getAllCategories();
            const filterSelect = document.getElementById('categoryFilter');
            const legendDiv = document.getElementById('categoryLegend');

            // Update filter dropdown
            filterSelect.innerHTML = '<option value="all">All Categories</option>';
            categories.forEach(category => {
                const option = document.createElement('option');
                option.value = category;
                option.textContent = category.charAt(0).toUpperCase() + category.slice(1);
                filterSelect.appendChild(option);
            });

            // Update legend
            legendDiv.innerHTML = '';
            if (categories.length === 0) {
                legendDiv.innerHTML = '<div style="font-size: 11px; color: #999;">No categories defined</div>';
            } else {
                categories.forEach(category => {
                    const item = document.createElement('div');
                    item.className = 'category-item';
                    item.innerHTML = `
                        <div class="category-color" style="background-color: ${getCategoryColor(category)}"></div>
                        <span>${category.charAt(0).toUpperCase() + category.slice(1)}</span>
                    `;
                    legendDiv.appendChild(item);
                });
            }
        }

        async function loadData() {
            try {
                // Fetch graph structure from API
                const graphResponse = await fetch('api.php/graph');
                const graphData = await graphResponse.json();

                // Fetch node status data from API
                const statusResponse = await fetch('api.php/status');
                const statusData = await statusResponse.json();

                // Create a status map for quick lookup
                const statusMap = {};
                if (statusData.statuses) {
                    statusData.statuses.forEach(status => {
                        statusMap[status.node_id] = status.status;
                    });
                }

                // Merge status into node data
                const nodes = (graphData.nodes || []).map(node => {
                    const nodeId = node.data.id;
                    const nodeStatus = statusMap[nodeId] || 'unknown';
                    return {
                        ...node,
                        data: {
                            ...node.data,
                            status: nodeStatus
                        }
                    };
                });

                // Initialize Cytoscape
                cy = cytoscape({
                    container: document.getElementById('cy'),

                    elements: {
                        nodes: nodes,
                        edges: graphData.edges || []
                    },

                    style: [
                        {
                            selector: 'node',
                            style: {
                                'shape': function(ele) {
                                    const category = ele.data('category');
                                    return categoryShapes[category] || 'ellipse';
                                },
                                'background-color': function(ele) {
                                    return getNodeColor(ele);
                                },
                                'background-image': function(ele) {
                                    const type = ele.data('type');
                                    const status = ele.data('status') || 'unknown';
                                    if (type && status) {
                                        return `img/${type}-${status}.png`;
                                    }
                                    return 'none';
                                },
                                'background-fit': 'contain',
                                'background-image-opacity': 0.8,
                                'label': function(ele) {
                                    const id = ele.data('id');
                                    const status = ele.data('status');
                                    const category = ele.data('category');
                                    const type = ele.data('type');

                                    let label = id;
                                    if (status) {
                                        label += `\n[${status}]`;
                                    }
                                    if (type) {
                                        label += `\n{${type}}`;
                                    }
                                    return label;
                                },
                                'color': '#333',
                                'text-valign': 'bottom',
                                'text-halign': 'center',
                                'text-margin-y': 5,
                                'width': 80,
                                'height': 80,
                                'font-size': 10,
                                'text-outline-width': 2,
                                'text-outline-color': '#fff',
                                'text-wrap': 'wrap',
                                'text-max-width': '120px'
                            }
                        },
                        {
                            selector: 'edge',
                            style: {
                                'width': 3,
                                'line-color': '#ccc',
                                'target-arrow-color': '#ccc',
                                'target-arrow-shape': 'triangle',
                                'curve-style': 'bezier',
                                'label': 'data(label)',
                                'font-size': 10,
                                'text-rotation': 'autorotate',
                                'text-margin-y': -10
                            }
                        },
                        {
                            selector: 'node:selected',
                            style: {
                                'background-color': '#ffc107',
                                'border-width': 3,
                                'border-color': '#ff9800'
                            }
                        }
                    ],

                    layout: {
                        name: 'cose',
                        animate: true,
                        animationDuration: 1000,
                        nodeRepulsion: 400000,
                        idealEdgeLength: 100,
                        edgeElasticity: 100,
                        nestingFactor: 5,
                        gravity: 80,
                        numIter: 1000,
                        initialTemp: 200,
                        coolingFactor: 0.95,
                        minTemp: 1.0
                    }
                });

                // Update stats
                document.getElementById('nodeCount').textContent = cy.nodes().length;
                document.getElementById('edgeCount').textContent = cy.edges().length;

                // Add click event to nodes
                cy.on('tap', 'node', function(evt) {
                    const node = evt.target;
                    const data = node.data();
                    console.log('Node clicked:', data);

                    // If node has a URL, you can optionally open it
                    if (data.url) {
                        const openUrl = confirm(`Open ${data.url}?`);
                        if (openUrl) {
                            window.open(data.url, '_blank');
                        }
                    }
                });

                // Add hover effect
                cy.on('mouseover', 'node', function(evt) {
                    evt.target.style('background-color', '#28a745');
                });

                cy.on('mouseout', 'node', function(evt) {
                    if (!evt.target.selected()) {
                        evt.target.style('background-color', getNodeColor(evt.target));
                    }
                });

                // Update category UI after loading
                updateCategoryUI();

            } catch (error) {
                console.error('Error loading data from API:', error);
                alert('Failed to load data from API. Make sure the API is running and accessible.');
            }
        }

        // Modal functions
        function openAddNodeModal() {
            document.getElementById('addNodeModal').style.display = 'block';
            document.getElementById('addNodeForm').reset();
        }

        function closeAddNodeModal() {
            document.getElementById('addNodeModal').style.display = 'none';
        }

        function openAddEdgeModal() {
            // Populate node list for autocomplete
            updateNodeList();
            document.getElementById('addEdgeModal').style.display = 'block';
            document.getElementById('addEdgeForm').reset();
        }

        function closeAddEdgeModal() {
            document.getElementById('addEdgeModal').style.display = 'none';
        }

        // Update node list for edge creation
        function updateNodeList() {
            const nodeList = document.getElementById('nodeList');
            nodeList.innerHTML = '';

            if (cy) {
                cy.nodes().forEach(node => {
                    const option = document.createElement('option');
                    option.value = node.data('id');
                    nodeList.appendChild(option);
                });
            }
        }

        // Close modals when clicking outside
        window.onclick = function(event) {
            const nodeModal = document.getElementById('addNodeModal');
            const edgeModal = document.getElementById('addEdgeModal');

            if (event.target === nodeModal) {
                closeAddNodeModal();
            }
            if (event.target === edgeModal) {
                closeAddEdgeModal();
            }
        }

        // Show notification
        function showNotification(message, type = 'success') {
            const notification = document.createElement('div');
            notification.className = `notification ${type}`;
            notification.textContent = message;
            document.body.appendChild(notification);

            setTimeout(() => {
                notification.remove();
            }, 3000);
        }

        // Handle node creation
        async function handleAddNode(event) {
            event.preventDefault();

            const nodeId = document.getElementById('nodeId').value.trim();
            const category = document.getElementById('nodeCategory').value;
            const type = document.getElementById('nodeType').value;
            const label = document.getElementById('nodeLabel').value.trim();
            const url = document.getElementById('nodeUrl').value.trim();
            const metadataStr = document.getElementById('nodeMetadata').value.trim();

            // Build node data
            const nodeData = {
                id: nodeId,
                category: category,
                type: type,
                label: label || nodeId
            };

            if (url) {
                nodeData.url = url;
            }

            // Parse additional metadata
            if (metadataStr) {
                try {
                    const metadata = JSON.parse(metadataStr);
                    Object.assign(nodeData, metadata);
                } catch (e) {
                    showNotification('Invalid JSON in metadata field', 'error');
                    return false;
                }
            }

            try {
                const authHeader = getAuthHeader();
                if (!authHeader) {
                    showNotification('Authentication cancelled', 'error');
                    return false;
                }

                const response = await fetch('api.php/nodes', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Authorization': authHeader
                    },
                    body: JSON.stringify({
                        id: nodeId,
                        data: nodeData
                    })
                });

                const result = await response.json();

                if (response.ok && result.success) {
                    showNotification(`Node "${nodeId}" created successfully!`, 'success');
                    closeAddNodeModal();
                    loadData(); // Reload graph
                } else {
                    showNotification(result.message || result.error || 'Failed to create node', 'error');
                }
            } catch (error) {
                console.error('Error creating node:', error);
                showNotification('Network error: ' + error.message, 'error');
            }

            return false;
        }

        // Handle edge creation
        async function handleAddEdge(event) {
            event.preventDefault();

            const edgeId = document.getElementById('edgeId').value.trim();
            const source = document.getElementById('edgeSource').value.trim();
            const target = document.getElementById('edgeTarget').value.trim();
            const label = document.getElementById('edgeLabel').value.trim();
            const metadataStr = document.getElementById('edgeMetadata').value.trim();

            // Build edge data
            const edgeData = {
                id: edgeId,
                source: source,
                target: target
            };

            if (label) {
                edgeData.label = label;
            }

            // Parse additional metadata
            if (metadataStr) {
                try {
                    const metadata = JSON.parse(metadataStr);
                    Object.assign(edgeData, metadata);
                } catch (e) {
                    showNotification('Invalid JSON in metadata field', 'error');
                    return false;
                }
            }

            try {
                const authHeader = getAuthHeader();
                if (!authHeader) {
                    showNotification('Authentication cancelled', 'error');
                    return false;
                }

                const response = await fetch('api.php/edges', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Authorization': authHeader
                    },
                    body: JSON.stringify({
                        id: edgeId,
                        source: source,
                        target: target,
                        data: edgeData
                    })
                });

                const result = await response.json();

                if (response.ok && result.success) {
                    showNotification(`Edge "${edgeId}" created successfully!`, 'success');
                    closeAddEdgeModal();
                    loadData(); // Reload graph
                } else {
                    showNotification(result.message || result.error || 'Failed to create edge', 'error');
                }
            } catch (error) {
                console.error('Error creating edge:', error);
                showNotification('Network error: ' + error.message, 'error');
            }

            return false;
        }

        // Load data on page load
        loadData();