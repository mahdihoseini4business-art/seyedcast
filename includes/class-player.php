<?php
/**
 * پلیر صوتی سفارشی
 */

if (!defined('ABSPATH')) {
    exit;
}

class SeyedCast_Player {

    private static $instance = null;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action('wp_footer', array($this, 'render_inline_player_scripts'));
    }

    /**
     * اسکریپت‌های درون‌خطی پلیر
     */
    public function render_inline_player_scripts() {
        if (!is_singular() && !is_post_type_archive('podcast_episode')) {
            return;
        }
        ?>
        <script>
        (function() {
            'use strict';
            
            var SeyedCastPlayer = {
                audio: null,
                isPlaying: false,
                currentEpisode: null,
                episodes: [],
                currentIndex: 0,
                
                init: function() {
                    this.audio = new Audio();
                    this.cacheElements();
                    this.bindEvents();
                    this.loadEpisodes();
                },
                
                cacheElements: function() {
                    this.playBtn = document.getElementById('seyedcast-play');
                    this.prevBtn = document.getElementById('seyedcast-prev');
                    this.nextBtn = document.getElementById('seyedcast-next');
                    this.progressBar = document.querySelector('.seyedcast-progress-bar');
                    this.progress = document.querySelector('.seyedcast-progress');
                    this.currentTime = document.querySelector('.seyedcast-current-time');
                    this.durationTime = document.querySelector('.seyedcast-duration-time');
                    this.volumeSlider = document.querySelector('.seyedcast-volume-slider input');
                    this.volumeBtn = document.getElementById('seyedcast-volume');
                    this.stickyPlayer = document.querySelector('.seyedcast-sticky-player');
                    this.playerTitle = document.querySelector('.seyedcast-player-title');
                    this.playerSubtitle = document.querySelector('.seyedcast-player-subtitle');
                    this.playerCover = document.querySelector('.seyedcast-player-cover');
                },
                
                bindEvents: function() {
                    var self = this;
                    
                    // دکمه پخش/توقف
                    if (this.playBtn) {
                        this.playBtn.addEventListener('click', function() {
                            self.togglePlay();
                        });
                    }
                    
                    // دکمه‌های قبلی/بعدی
                    if (this.prevBtn) {
                        this.prevBtn.addEventListener('click', function() {
                            self.playPrev();
                        });
                    }
                    
                    if (this.nextBtn) {
                        this.nextBtn.addEventListener('click', function() {
                            self.playNext();
                        });
                    }
                    
                    // نوار پیشرفت
                    if (this.progressBar) {
                        this.progressBar.addEventListener('click', function(e) {
                            self.seekTo(e);
                        });
                    }
                    
                    // کنترل صدا
                    if (this.volumeSlider) {
                        this.volumeSlider.addEventListener('input', function() {
                            self.setVolume(this.value);
                        });
                    }
                    
                    if (this.volumeBtn) {
                        this.volumeBtn.addEventListener('click', function() {
                            self.toggleMute();
                        });
                    }
                    
                    // رویدادهای صوتی
                    this.audio.addEventListener('timeupdate', function() {
                        self.updateProgress();
                    });
                    
                    this.audio.addEventListener('loadedmetadata', function() {
                        self.updateDuration();
                    });
                    
                    this.audio.addEventListener('ended', function() {
                        self.playNext();
                    });
                    
                    // دکمه‌های پخش روی کارت‌ها
                    document.addEventListener('click', function(e) {
                        var playBtn = e.target.closest('.seyedcast-play-btn');
                        if (playBtn) {
                            e.preventDefault();
                            var audioUrl = playBtn.getAttribute('data-audio');
                            var card = playBtn.closest('.seyedcast-episode-card');
                            self.loadEpisode(audioUrl, card);
                        }
                    });
                    
                    // کلیدهای میانبر صفحه کلید
                    document.addEventListener('keydown', function(e) {
                        if (e.target.tagName === 'INPUT' || e.target.tagName === 'TEXTAREA') {
                            return;
                        }
                        
                        switch(e.code) {
                            case 'Space':
                                e.preventDefault();
                                self.togglePlay();
                                break;
                            case 'ArrowLeft':
                                self.seekBackward(15);
                                break;
                            case 'ArrowRight':
                                self.seekForward(15);
                                break;
                        }
                    });
                },
                
                loadEpisodes: function() {
                    var cards = document.querySelectorAll('.seyedcast-episode-card');
                    var self = this;
                    
                    cards.forEach(function(card, index) {
                        var audioUrl = card.getAttribute('data-audio-url');
                        if (audioUrl) {
                            self.episodes.push({
                                url: audioUrl,
                                title: card.querySelector('.seyedcast-card-title')?.textContent || '',
                                cover: card.querySelector('.seyedcast-card-cover img')?.src || '',
                                element: card
                            });
                        }
                    });
                },
                
                loadEpisode: function(url, card) {
                    if (!url) return;
                    
                    this.audio.src = url;
                    this.currentEpisode = {
                        url: url,
                        title: card?.querySelector('.seyedcast-card-title')?.textContent || '',
                        cover: card?.querySelector('.seyedcast-card-cover img')?.src || ''
                    };
                    
                    // پیدا کردن ایندکس در لیست
                    for (var i = 0; i < this.episodes.length; i++) {
                        if (this.episodes[i].url === url) {
                            this.currentIndex = i;
                            break;
                        }
                    }
                    
                    this.updatePlayerInfo();
                    this.play();
                    this.recordPlay(card?.getAttribute('data-episode-id'));
                },
                
                play: function() {
                    this.audio.play();
                    this.isPlaying = true;
                    this.updatePlayButton();
                    this.showStickyPlayer();
                },
                
                pause: function() {
                    this.audio.pause();
                    this.isPlaying = false;
                    this.updatePlayButton();
                },
                
                togglePlay: function() {
                    if (this.isPlaying) {
                        this.pause();
                    } else {
                        this.play();
                    }
                },
                
                playNext: function() {
                    if (this.episodes.length === 0) return;
                    
                    this.currentIndex = (this.currentIndex + 1) % this.episodes.length;
                    var episode = this.episodes[this.currentIndex];
                    this.loadEpisode(episode.url, episode.element);
                },
                
                playPrev: function() {
                    if (this.episodes.length === 0) return;
                    
                    this.currentIndex = (this.currentIndex - 1 + this.episodes.length) % this.episodes.length;
                    var episode = this.episodes[this.currentIndex];
                    this.loadEpisode(episode.url, episode.element);
                },
                
                seekTo: function(e) {
                    var rect = this.progressBar.getBoundingClientRect();
                    var percent = (e.clientX - rect.left) / rect.width;
                    this.audio.currentTime = percent * this.audio.duration;
                },
                
                seekForward: function(seconds) {
                    this.audio.currentTime = Math.min(this.audio.currentTime + seconds, this.audio.duration);
                },
                
                seekBackward: function(seconds) {
                    this.audio.currentTime = Math.max(this.audio.currentTime - seconds, 0);
                },
                
                setVolume: function(value) {
                    this.audio.volume = value / 100;
                    this.updateVolumeIcon();
                },
                
                toggleMute: function() {
                    this.audio.muted = !this.audio.muted;
                    this.updateVolumeIcon();
                },
                
                updateProgress: function() {
                    if (!this.audio.duration) return;
                    
                    var percent = (this.audio.currentTime / this.audio.duration) * 100;
                    this.progress.style.width = percent + '%';
                    this.currentTime.textContent = this.formatTime(this.audio.currentTime);
                },
                
                updateDuration: function() {
                    this.durationTime.textContent = this.formatTime(this.audio.duration);
                },
                
                updatePlayButton: function() {
                    var icon = this.playBtn.querySelector('.dashicons');
                    if (this.isPlaying) {
                        icon.classList.remove('dashicons-controls-play');
                        icon.classList.add('dashicons-controls-pause');
                    } else {
                        icon.classList.remove('dashicons-controls-pause');
                        icon.classList.add('dashicons-controls-play');
                    }
                },
                
                updateVolumeIcon: function() {
                    var icon = this.volumeBtn.querySelector('.dashicons');
                    icon.classList.remove('dashicons-controls-volumeon', 'dashicons-controls-volumeoff', 'dashicons-controls-volume3');
                    
                    if (this.audio.muted || this.audio.volume === 0) {
                        icon.classList.add('dashicons-controls-volumeoff');
                    } else if (this.audio.volume < 0.5) {
                        icon.classList.add('dashicons-controls-volume3');
                    } else {
                        icon.classList.add('dashicons-controls-volumeon');
                    }
                },
                
                updatePlayerInfo: function() {
                    if (!this.currentEpisode) return;
                    
                    if (this.playerTitle) {
                        this.playerTitle.textContent = this.currentEpisode.title;
                    }
                    
                    if (this.playerCover && this.currentEpisode.cover) {
                        this.playerCover.src = this.currentEpisode.cover;
                    }
                },
                
                showStickyPlayer: function() {
                    if (this.stickyPlayer) {
                        this.stickyPlayer.classList.add('active');
                    }
                },
                
                formatTime: function(seconds) {
                    if (isNaN(seconds)) return '0:00';
                    
                    var hours = Math.floor(seconds / 3600);
                    var minutes = Math.floor((seconds % 3600) / 60);
                    var secs = Math.floor(seconds % 60);
                    
                    if (hours > 0) {
                        return hours + ':' + this.pad(minutes) + ':' + this.pad(secs);
                    }
                    return minutes + ':' + this.pad(secs);
                },
                
                pad: function(num) {
                    return num < 10 ? '0' + num : num;
                },
                
                recordPlay: function(episodeId) {
                    if (!episodeId || typeof seyedcast_ajax === 'undefined') return;
                    
                    var xhr = new XMLHttpRequest();
                    xhr.open('POST', seyedcast_ajax.url, true);
                    xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
                    xhr.send('action=seyedcast_play_episode&episode_id=' + episodeId + '&nonce=' + seyedcast_ajax.nonce);
                }
            };
            
            // مقداردهی اولیه
            document.addEventListener('DOMContentLoaded', function() {
                SeyedCastPlayer.init();
            });
        })();
        </script>
        <?php
    }
}
