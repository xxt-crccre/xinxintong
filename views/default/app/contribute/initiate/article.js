xxtApp.config(['$routeProvider', function ($routeProvider) {
    $routeProvider.when('/rest/app/contribute/initiate/article', {
        templateUrl: '/views/default/app/contribute/initiate/edit.html',
        controller: 'editCtrl',
    }).when('/rest/app/contribute/initiate/reviewlog', {
        templateUrl: '/views/default/app/contribute/initiate/reviewlog.html',
        controller: 'reviewlogCtrl',
    });
}]);
xxtApp.controller('initiateCtrl', ['$scope', '$location', '$modal', 'http2', 'Article', 'Entry', 'Reviewlog', function ($scope, $location, $modal, http2, Article, Entry, Reviewlog) {
    $scope.phases = { 'I': '投稿', 'R': '审核', 'T': '版面' };
    $scope.mpid = $location.search().mpid;
    $scope.entry = $location.search().entry;
    $scope.id = $location.search().id;
    $scope.Article = new Article('initiate', $scope.mpid, $scope.entry);
    $scope.Entry = new Entry($scope.mpid, $scope.entry);
    $scope.Article.get($scope.id).then(function (data) {
        $scope.editing = data;
    }).then(function () {
        $scope.Entry.get().then(function (data) {
            var i, j, ch, mapSubChannels = {};
            $scope.editing.subChannels = [];
            $scope.entryApp = data;
            for (i = 0, j = data.subChannels.length; i < j; i++) {
                ch = data.subChannels[i];
                mapSubChannels[ch.id] = ch;
            }
            for (i = 0, j = $scope.editing.channels.length; i < j; i++) {
                ch = $scope.editing.channels[i];
                mapSubChannels[ch.id] && $scope.editing.subChannels.push(ch);
            }
        });
    });
    $scope.back = function (event) {
        event.preventDefault();
        location.href = '/rest/app/contribute/initiate?mpid=' + $scope.mpid + '&entry=' + $scope.entry;
    };
    $scope.$watch('jsonParams', function (nv) {
        if (nv && nv.length) {
            var params = JSON.parse(decodeURIComponent(nv.replace(/\+/, '%20')));
            $scope.fid = params.fid;
            $scope.needReview = params.needReview;
            if ($scope.needReview === 'Y')
                $scope.picGalleryUrl = '/kcfinder/browse.php?lang=zh-cn&type=图片&mpid=' + $scope.mpid;
            else
                $scope.picGalleryUrl = '/kcfinder/browse.php?lang=zh-cn&type=图片&mpid=' + $scope.fid;
        }
    });
    $scope.edit = function (event, article) {
        if (article._cascade === true)
            $scope.editing = article;
        else
            $scope.Article.get(article.id).then(function (rsp) {
                article._cascade = true;
                article.channels = rsp.channels;
                $scope.editing = article;
            });
    };
    $scope.setPic = function () {
        $scope.$broadcast('picgallery.open', function (url) {
            var t = (new Date()).getTime();
            url += '?_=' + t;
            $scope.editing.pic = url;
            $scope.Article.update($scope.editing, 'pic');
        }, false);
    };
    $scope.removePic = function () {
        $scope.editing.pic = '';
        $scope.Article.update($scope.editing, 'pic');
    };
    window.onbeforeunload = function (e) {
        var message;
        if ($scope.bodyModified) {
            message = '已经修改的正文还没有保存',
            e = e || window.event;
            if (e) {
                e.returnValue = message;
            }
            return message;
        }
    };
    $scope.onBodyChange = function () {
        $scope.bodyModified = true;
    };
    $scope.$on('tinymce.multipleimage.open', function (event, callback) {
        $scope.$broadcast('picgallery.open', callback, true, true);
    });
    $scope.update = function (name) {
        $scope.Article.update($scope.editing, name);
        name === 'body' && ($scope.bodyModified = false);
    };
    $scope.$on('sub-channel.xxt.combox.done', function (event, aSelected) {
        var i, j, c, params = { channels: [], matter: { id: $scope.editing.id, type: 'article' } };
        for (i = 0, j = aSelected.length; i < j; i++) {
            c = aSelected[i];
            params.channels.push({ id: c.id });
        }
        $scope.Article.addChannels(params).then(function () {
            for (i = 0, j = aSelected.length; i < j; i++) {
                c = aSelected[i];
                $scope.editing.subChannels.push({ id: c.id, title: c.title });
            }
        });
    });
    $scope.$on('sub-channel.xxt.combox.del', function (event, removed) {
        $scope.Article.delChannel($scope.editing.id, removed.id).then(function () {
            var i = $scope.editing.subChannels.indexOf(removed);
            $scope.editing.subChannels.splice(i, 1);
        });
    });
    $scope.$on('tag.xxt.combox.done', function (event, aSelected) {
        var aNewTags = [];
        for (var i in aSelected) {
            var existing = false;
            for (var j in $scope.editing.tags) {
                if (aSelected[i].title === $scope.editing.tags[j].title) {
                    existing = true;
                    break;
                }
            }
            !existing && aNewTags.push(aSelected[i]);
        }
        http2.post('/rest/mp/matter/article/addTag?id=' + $scope.id, aNewTags, function (rsp) {
            $scope.editing.tags = $scope.editing.tags.concat(aNewTags);
        });
    });
    $scope.$on('tag.xxt.combox.add', function (event, newTag) {
        var oNewTag = { title: newTag };
        http2.post('/rest/mp/matter/article/addTag?id=' + $scope.id, [oNewTag], function (rsp) {
            $scope.editing.tags.push(oNewTag);
        });
    });
    $scope.$on('tag.xxt.combox.del', function (event, removed) {
        http2.post('/rest/mp/matter/article/removeTag?id=' + $scope.id, [removed], function (rsp) {
            $scope.editing.tags.splice($scope.editing.tags.indexOf(removed), 1);
        });
    });
    http2.get('/rest/mp/matter/tag?resType=article', function (rsp) {
        $scope.tags = rsp.data;
    });
    $scope.finish = function () {
        $scope.editing.finished = 'Y';
        $scope.Article.update($scope.editing, 'finished');
    };
    $scope.remove = function () {
        if (window.confirm('确认删除？')) {
            $scope.Article.remove($scope.editing).then(function (data) {
                location.href = '/rest/app/contribute/initiate?mpid=' + $scope.mpid + '&entry=' + $scope.entry;
            });
        }
    };
    $scope.forward = function () {
        $modal.open({
            templateUrl: 'review-list.html',
            controller: ['$scope', '$modalInstance', 'reviewers', function ($scope, $mi, reviewers) {
                $scope.reviewers = reviewers;
                $scope.data = { selected: '0' };
                $scope.cancel = function () {
                    $mi.dismiss();
                };
                $scope.ok = function () {
                    $scope.data.selected ? $mi.close(reviewers[$scope.data.selected]) : $mi.dismiss();
                };
            }],
            resolve: {
                reviewers: function () { return $scope.entryApp.reviewers; }
            },
            backdrop: 'static',
        }).result.then(function (who) {
            $scope.Article.forward($scope.editing, who.identity, 'R').then(function () {
                location.href = '/rest/app/contribute/initiate?mpid=' + $scope.mpid + '&entry=' + $scope.entry;
            });
        });
    };
    $scope.Reviewlog = new Reviewlog('initiate', $scope.mpid, { type: 'article', id: $scope.id });
    $scope.Reviewlog.list().then(function (data) {
        $scope.logs = data;
    });
}]);
