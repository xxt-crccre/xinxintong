xxtApp.controller('articleCtrl', ['$scope', '$window', '$modal', 'http2', function($scope, $window, $modal, http2) {
    var getArticles = function() {
        var options = {
            channel: $scope.selectedChannelsId,
            tag: $scope.selectedTagsId,
            order: $scope.order
        };
        var url = '/rest/mp/matter/article/get?' + $scope.page.toString();
        $scope.fromParent && $scope.fromParent === 'Y' && (options.src = 'p');
        http2.post(url, options, function(rsp) {
            $scope.articles = rsp.data[0];
            rsp.data[1] !== undefined && ($scope.page.total = rsp.data[1]);
        });
    };
    var getInitData = function() {
        http2.get('/rest/mp/mpaccount/get', function(rsp) {
            $scope.mpa = rsp.data;
            $scope.hasParent = (rsp.data.parent_mpid && rsp.data.parent_mpid.length) ? 'Y' : 'N';
        });
        http2.get('/rest/mp/matter/tag?resType=article', function(rsp) {
            $scope.tags = rsp.data;
            getArticles();
        });
        http2.get('/rest/mp/matter/channel/get?cascade=N', function(rsp) {
            $scope.channels = rsp.data;
        });
    };
    $scope.selectedChannels = [];
    $scope.selectedChannelsId = [];
    $scope.selectedTags = [];
    $scope.selectedTagsId = [];
    $scope.order = 'time';
    $scope.page = {
        at: 1,
        size: 30,
        toString: function() {
            return 'page=' + this.at + '&size=' + this.size;
        }
    };
    $scope.create = function() {
        http2.get('/rest/mp/matter/article/create', function(rsp) {
            location.href = '/rest/mp/matter/article?id=' + rsp.data;
        });
    };
    $scope.upload = function() {
        $modal.open({
            templateUrl: 'uploadArticle.html',
            controller: ['$scope', '$modalInstance', '$timeout', function($scope, $mi) {
                $scope.cancel = function() {
                    $mi.dismiss();
                };
                $scope.ok = function() {
                    $scope.uploading = true;
                    var r = new Resumable({
                        target: '/rest/mp/matter/article/uploadAndCreate',
                        testChunks: false,
                    });
                    r.on('fileAdded', function(file, event) {
                        console.log('file Added and begin upload.');
                        r.upload();
                    });
                    r.on('progress', function() {
                        console.log('progress.');
                    });
                    r.on('complete', function() {
                        console.log('complete.');
                        var f, lastModified, posted;
                        f = r.files[0].file;
                        lastModified = f.lastModified ? f.lastModified : (f.lastModifiedDate ? f.lastModifiedDate.getTime() : 0);
                        posted = {
                            file: {
                                uniqueIdentifier: r.files[0].uniqueIdentifier,
                                name: f.name,
                                size: f.size,
                                type: f.type,
                                lastModified: lastModified
                            }
                        };
                        http2.post('/rest/mp/matter/article/uploadAndCreate?state=done', posted, function(rsp) {
                            $scope.uploading = false;
                            $mi.close(rsp.data);
                        });
                    });
                    r.addFile(document.querySelector('#fileUpload').files[0]);
                };
            }],
            backdrop: 'static',
        }).result.then(function(data) {
            location.href = '/rest/mp/matter/article?id=' + data;
        });
    };
    $scope.edit = function(article) {
        location.href = '/rest/mp/matter/article?id=' + article.id;
    };
    $scope.remove = function(event, article, index) {
        event.preventDefault();
        event.stopPropagation();
        if ($window.confirm('确认删除？'))
            http2.get('/rest/mp/matter/article/remove?id=' + article.id, function(rsp) {
                $scope.articles.splice(index, 1);
            });
    };
    $scope.doSearch = function() {
        getArticles();
    };
    $scope.$on('channel.xxt.combox.done', function(event, aSelected) {
        for (var i in aSelected) {
            if ($scope.selectedChannels.indexOf(aSelected[i].title) === -1) {
                $scope.selectedChannels.push(aSelected[i].title);
                $scope.selectedChannelsId.push(aSelected[i].id);
            }
        }
        getArticles();
    });
    $scope.$on('channel.xxt.combox.del', function(event, removed) {
        var i = $scope.selectedChannels.indexOf(removed);
        $scope.selectedChannels.splice(i, 1);
        $scope.selectedChannelsId.splice(i, 1);
        getArticles();
    });
    $scope.$on('tag.xxt.combox.done', function(event, aSelected) {
        for (var i in aSelected) {
            if ($scope.selectedTags.indexOf(aSelected[i].title) === -1) {
                $scope.selectedTags.push(aSelected[i].title);
                $scope.selectedTagsId.push(aSelected[i].id);
            }
        }
        getArticles();
    });
    $scope.$on('tag.xxt.combox.del', function(event, removed) {
        var i = $scope.selectedTags.indexOf(removed);
        $scope.selectedTags.splice(i, 1);
        $scope.selectedTagsId.splice(i, 1);
        getArticles();
    });
    getInitData();
}]);