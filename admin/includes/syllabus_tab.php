
<?php
// syllabus_tab.php
require_once __DIR__ . '/header.php';

// Check if $syllabus variable is set and is an array
$syllabus = $syllabus ?? [];
?>
    <div class="flex justify-between items-center mb-4">
        <h2 class="text-lg font-medium text-gray-900">Uploaded Syllabus</h2>
    </div>
    
    <?php if (!empty($syllabus)): ?>
        <div class="bg-white shadow overflow-hidden sm:rounded-md">
            <ul class="divide-y divide-gray-200">
                <?php foreach ($syllabus as $item): ?>
                    <li class="hover:bg-gray-50">
                        <div class="px-4 py-4 sm:px-6">
                            <div class="flex items-center justify-between">
                                <div class="flex-1 min-w-0">
                                    <div class="flex items-center">
                                        <p class="text-sm font-medium text-blue-600 truncate">
                                            <?= htmlspecialchars($item['title']) ?>
                                        </p>
                                        <span class="ml-2 px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">
                                            <?= strtoupper(pathinfo($item['file_path'], PATHINFO_EXTENSION)) ?>
                                        </span>
                                    </div>
                                    <?php if (!empty($item['description'])): ?>
                                        <p class="mt-1 text-sm text-gray-500 line-clamp-2">
                                            <?= htmlspecialchars($item['description']) ?>
                                        </p>
                                    <?php endif; ?>
                                    <div class="mt-2 flex items-center text-sm text-gray-500">
                                        <i class="far fa-calendar-alt mr-1"></i>
                                        <span>Uploaded on <?= date('M j, Y', strtotime($item['created_at'])) ?></span>
                                    </div>
                                </div>
                                <div class="ml-4 flex-shrink-0 flex space-x-2">
                                    <a href="<?= htmlspecialchars($item['file_path']) ?>" 
                                       class="inline-flex items-center px-3 py-1.5 border border-transparent text-xs font-medium rounded text-blue-700 bg-blue-100 hover:bg-blue-200 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500"
                                       target="_blank"
                                       title="View">
                                        <i class="fas fa-eye mr-1"></i> View
                                    </a>
                                    <a href="<?= htmlspecialchars($item['file_path']) ?>" 
                                       class="inline-flex items-center px-3 py-1.5 border border-gray-300 shadow-sm text-xs font-medium rounded text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500"
                                       download
                                       title="Download">
                                        <i class="fas fa-download mr-1"></i> Download
                                    </a>
                                    <a href="delete_syllabus.php?id=<?= $item['id'] ?>" 
                                       class="inline-flex items-center px-3 py-1.5 border border-transparent text-xs font-medium rounded text-red-700 bg-red-100 hover:bg-red-200 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500"
                                       onclick="return confirm('Are you sure you want to delete this syllabus?')"
                                       title="Delete">
                                        <i class="fas fa-trash mr-1"></i> Delete
                                    </a>
                                </div>
                            </div>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php else: ?>
        <div class="text-center py-12 border-2 border-dashed border-gray-300 rounded-lg">
            <i class="fas fa-book-open text-4xl text-gray-400 mb-3"></i>
            <h3 class="mt-2 text-sm font-medium text-gray-900">No syllabus uploaded</h3>
            <p class="mt-1 text-sm text-gray-500">Get started by uploading a new syllabus.</p>
        </div>
    <?php endif; ?>
<?php require_once __DIR__ . '/footer.php'; ?>
