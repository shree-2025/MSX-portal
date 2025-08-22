

<div class="mb-8">

    <div class="flex justify-between items-center mb-4">
        <h2 class="text-lg font-medium text-gray-900">Uploaded Notes</h2>
    </div>
    
    <?php if (!empty($notes)): ?>
        <div class="grid gap-4 sm:grid-cols-1 md:grid-cols-2 lg:grid-cols-3">
            <?php foreach ($notes as $note): 
                $fileExt = strtoupper(pathinfo($note['file_path'], PATHINFO_EXTENSION));
                $iconClass = 'fa-file';
                $bgColor = 'bg-blue-100';
                $textColor = 'text-blue-800';
                
                // Set icon and color based on file type
                if (in_array($fileExt, ['PDF'])) {
                    $iconClass = 'fa-file-pdf';
                    $bgColor = 'bg-red-100';
                    $textColor = 'text-red-800';
                } elseif (in_array($fileExt, ['DOC', 'DOCX'])) {
                    $iconClass = 'fa-file-word';
                    $bgColor = 'bg-blue-100';
                    $textColor = 'text-blue-800';
                } elseif (in_array($fileExt, ['XLS', 'XLSX'])) {
                    $iconClass = 'fa-file-excel';
                    $bgColor = 'bg-green-100';
                    $textColor = 'text-green-800';
                } elseif (in_array($fileExt, ['JPG', 'JPEG', 'PNG', 'GIF'])) {
                    $iconClass = 'fa-file-image';
                    $bgColor = 'bg-purple-100';
                    $textColor = 'text-purple-800';
                }
            ?>
                <div class="bg-white overflow-hidden shadow rounded-lg">
                    <div class="p-5">
                        <div class="flex items-center">
                            <div class="flex-shrink-0">
                                <div class="flex items-center justify-center h-12 w-12 rounded-md <?= $bgColor ?> <?= $textColor ?>">
                                    <i class="fas <?= $iconClass ?> text-xl"></i>
                                </div>
                            </div>
                            <div class="ml-4 flex-1">
                                <h3 class="text-lg font-medium text-gray-900 truncate"><?= htmlspecialchars($note['title']) ?></h3>
                                <p class="text-sm text-gray-500 mt-1 line-clamp-2">
                                    <?= !empty($note['description']) ? htmlspecialchars($note['description']) : 'No description' ?>
                                </p>
                            </div>
                        </div>
                        <div class="mt-4 flex items-center justify-between text-sm text-gray-500">
                            <div class="flex items-center">
                                <i class="far fa-calendar-alt mr-1"></i>
                                <span><?= date('M j, Y', strtotime($note['created_at'])) ?></span>
                            </div>
                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-gray-100 text-gray-800">
                                .<?= $fileExt ?>
                            </span>
                        </div>
                        <div class="mt-4 flex space-x-2">
                            <a href="<?= htmlspecialchars($note['file_path']) ?>" 
                               target="_blank"
                               class="inline-flex items-center px-3 py-1.5 border border-transparent text-xs font-medium rounded text-blue-700 bg-blue-100 hover:bg-blue-200 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                <i class="fas fa-eye mr-1"></i> View
                            </a>
                            <a href="<?= htmlspecialchars($note['file_path']) ?>" 
                               download
                               class="inline-flex items-center px-3 py-1.5 border border-gray-300 shadow-sm text-xs font-medium rounded text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                <i class="fas fa-download mr-1"></i> Download
                            </a>
                            <a href="delete_material.php?type=notes&id=<?= $note['id'] ?>" 
                               onclick="return confirm('Are you sure you want to delete these notes?')"
                               class="inline-flex items-center px-3 py-1.5 border border-transparent text-xs font-medium rounded text-red-700 bg-red-100 hover:bg-red-200 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500">
                                <i class="fas fa-trash mr-1"></i> Delete
                            </a>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <div class="text-center py-12 border-2 border-dashed border-gray-300 rounded-lg">
            <i class="fas fa-sticky-note text-4xl text-gray-400 mb-3"></i>
            <h3 class="mt-2 text-sm font-medium text-gray-900">No notes uploaded</h3>
            <p class="mt-1 text-sm text-gray-500">Get started by uploading new notes.</p>
        </div>
    <?php endif; ?>
</div>
